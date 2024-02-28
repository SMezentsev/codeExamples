<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\search\SupplierSearch;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\web\NotAcceptableHttpException;

class SuppliersController extends ActiveController
{
    use ControllerTrait;
    
    /**
     * @var string
     */
    public $modelClass = 'app\modules\itrack\models\Suppliers';
    
    /**
     * @var array
     */
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'suppliers',
    ];
    
    /**
     * @return array
     */
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['update']);
            unset($actions['delete']);
            unset($actions['create']);
        }
        
        return $actions;
    }
    
    /**
     * @param string $action
     * @param null   $model
     * @param array  $params
     *
     * @throws NotAcceptableHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
            case 'delete':
                if (!\Yii::$app->user->can('reference-suppliers-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-suppliers')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-suppliers') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    /**
     * @return array|ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $searchModel = new SupplierSearch();
        $dataProvider = $searchModel->search(\Yii::$app->request->queryParams);
        
        if (\Yii::$app->request->getQueryParam('combo') === 'true') {
            return $this->comboDataProvider($dataProvider->query->all());
        }
        
        return $dataProvider;
    }
    
    /**
     * @param array $data
     *
     * @return array
     */
    protected function comboDataProvider(array $data)
    {
        $formated = [
            'suppliers' => array_map(function ($field) {
                return ["uid" => $field["id"], "id" => $field["id"], "name" => $field["name"]];
            }, $data),
        ];
        
        $provider = new ArrayDataProvider([
            'allModels'  => $formated,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
        
        return $provider->getModels();
    }
}
