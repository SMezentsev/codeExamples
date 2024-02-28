<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;

class VarsController extends ActiveController
{
    
    public $modelClass = 'app\modules\itrack\models\Vars';
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
    
}
