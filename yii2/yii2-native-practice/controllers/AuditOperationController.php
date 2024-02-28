<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\data\ActiveDataProvider;

class AuditOperationController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\AuditOperation';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'AuditOperation',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['delete'], $actions['create'], $actions['update']);
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $model = $this->modelClass;
        $dataProvider = new ActiveDataProvider([
            'query'      => $model::find(),
            'pagination' => false,
        ]);
        
        return $dataProvider;
    }
    
}
