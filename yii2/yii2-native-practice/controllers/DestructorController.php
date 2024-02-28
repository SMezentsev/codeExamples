<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 19.01.16
 * Time: 14:51
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\NotAcceptableHttpException;

class DestructorController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Destructor';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'destructors',
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
        
        /** @var ActiveQuery $query */
        $query = $modelClass::find();
        $query->andFilterWhere(['ilike', 'name', \Yii::$app->request->get('name')]);
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['destructors' => array_map(function ($v) {
                return ["id" => $v["id"], "uid" => $v["id"], "name" => $v["name"], "inn" => $v["inn"]];
            }, $query->orderBy('name')->all())];
        }
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_DESTRUCTOR, 'Просмотр списка утилизирующих организаций', [["field"=>"Наименование","value"=>\Yii::$app->request->get('name')]]);
        
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
                if (!\Yii::$app->user->can('reference-destructors-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-destructors')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-destructors') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
