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

class Rafarma4Controller extends \app\modules\itrack\controllers\ExtdataController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\rafarma\Report4';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'pallet-data',
    ];
    
    public function prepareDataProvider()
    {
        $modelClass = $this->modelClass;
        $objectSort = new $modelClass;
        $dataProvider = $objectSort->search(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'object_uid', \Yii::$app->user->identity->object_uid]);
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр данных pallet-data', []);
        
        return $dataProvider;
    }
    
    public function actionMulti()
    {
        $data = \Yii::$app->request->getBodyParam('data');
        if (!is_array($data)) {
            throw new \yii\web\BadRequestHttpException('Переменная data - должна быть массивом');
        }
        $mc = $this->modelClass;
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_DATA, 'Сохранение данных в pallet-data', [["field" => "Массив значений", "value" => $data]]);
        
        return $mc::addMulti($data);
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
