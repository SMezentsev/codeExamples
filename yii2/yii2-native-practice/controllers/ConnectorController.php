<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\connectors\Connector;
use yii\web\NotAcceptableHttpException;

class ConnectorController extends ActiveController
{
    
    use ControllerTrait;
    
    /**
     * @var array
     */
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'connector',
    ];
    
    /**
     * @var string
     */
    public $modelClass = 'app\modules\itrack\models\connectors\Connector';
    
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
        if (!\Yii::$app->user->can('see-all-objects')) {
            throw new NotAcceptableHttpException("Запрет на выполнение операции");
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    /**
     * @return array
     */
    public function actionTypes()
    {
        return ['types' => array_map(function ($id, $name) {
            return ['uid' => $id, 'id' => $id, 'name' => $name];
        }, array_keys(Connector::$types), array_values(Connector::$types))];
    }
    
    
    /**
     * @return \yii\data\ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $objectSort = new Connector();
        $dataProvider = $objectSort->search(\Yii::$app->request->getQueryParams());
        
        return $dataProvider;
    }
}
