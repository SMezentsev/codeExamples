<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\NotAcceptableHttpException;

class ManufacturerMdController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\ManufacturerMd';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'manufacturer_md',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        if (SERVER_RULE != SERVER_RULE_RF) {
            unset($actions['update']);
            unset($actions['delete']);
        }
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $modelClass = $this->modelClass;
        $params = \Yii::$app->request->getQueryParams();
        
        /** @var ActiveQuery $query */
        $query = $modelClass::find();
        
        $query->leftJoin('manufacturer', 'manufacturer.id = manufacturer_uid');
        if (!empty($params["ownerId"])) {
            $query->andWhere(["ownerid" => $params["ownerId"]]);
        }
        
        
        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
            case 'delete':
                if (!\Yii::$app->user->can('reference-manufacturers-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-manufacturers')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-manufacturers') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
