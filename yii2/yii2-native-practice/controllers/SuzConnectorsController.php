<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 6:55
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;

class SuzConnectorsController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\SuzConnectors';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'SuzConnectors',
    ];
    
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        //unset($actions['delete']);
        return $actions;
    }
    
    
    public function actionCheck($url, $client_token, $omsId)
    {
        return \app\modules\itrack\components\Suz::checkPing($url, $client_token, $omsId);
    }
    
    public function prepareDataProvider()
    {
        $model = $this->modelClass;
        $params = \Yii::$app->request->getQueryParams();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $model::find(),
            'pagination' => false,
        ]);
        
        return $dataProvider;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            default:
                if (!\Yii::$app->user->can('reference-objects-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
