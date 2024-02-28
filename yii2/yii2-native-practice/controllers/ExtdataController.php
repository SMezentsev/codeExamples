<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 16.04.2015
 * Time: 6:18
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Extdata;

class ExtdataController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Extdata';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'extdata',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['delete']);
        unset($actions['update']);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            //unset($actions['create']);
            //unset($actions['index']);
        }
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $objectSort = new Extdata();
        $dataProvider = $objectSort->search(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'id', \Yii::$app->user->identity->object_uid]);
        }
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, "Просмотр внешних данных", []);
        return $dataProvider;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'index':
            case 'view':
            case 'delete':
            case 'create':
            case 'update':
//                if (!\Yii::$app->user->can('reference-objects-crud'))
//                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
