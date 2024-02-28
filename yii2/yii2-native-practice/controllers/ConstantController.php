<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;

class ConstantController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Constants';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'Vars',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['delete']);
        
        return $actions;
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
    
    public function actionGetByName($name)
    {
        $mc = $this->modelClass;
        
        return ['name' => $name, 'value' => $mc::get($name)];
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        if (!\Yii::$app->user->can('see-all-objects')) {
            throw new \yii\web\NotAcceptableHttpException('Запрет на выполнение операции');
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}
