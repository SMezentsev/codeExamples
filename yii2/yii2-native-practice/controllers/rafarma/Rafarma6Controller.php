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

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\components\boxy\ControllerTrait;

class Rafarma6Controller extends \app\modules\itrack\controllers\ExtdataController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\rafarma\Report6';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'brak',
    ];
    
    public function prepareDataProvider()
    {
        $modelClass = $this->modelClass;
        $objectSort = new $modelClass;
        $dataProvider = $objectSort->search(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'object_uid', \Yii::$app->user->identity->object_uid]);
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Брак', []);
        
        return $dataProvider;
    }
    
    public function actionUpd()
    {
        $params = \Yii::$app->request->getBodyParams();
        $mc = $this->modelClass;
        $r = $mc::findOne(['params1' => $params["series"], 'params2' => $params["zakaz_number"]]);
        if (empty($r)) {
            $r = new $mc;
        }
        $r->load($params, '');
        //потом
        $r->data1 = $params["brak"];
        $r->save();
        $r->refresh();
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_DATA, 'Добавление данных в отчет Брак', [["field" => "Массив значений", "value" => $params]]);
        
        return $r;
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
