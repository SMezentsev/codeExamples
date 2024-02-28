<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\components\Notify\Fns\Interfaces\FnsNotifyServiceInterface;
use app\modules\itrack\models\AuditLog;
use app\modules\itrack\models\AuditOperation;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Notify;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;

class NotifyController extends ActiveController
{
    use ControllerTrait;

    public $modelClass = 'app\modules\itrack\models\Notify';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'notify',
    ];

    /**
     * @var FnsNotifyServiceInterface
     */
    private $notifyService;


    public function __construct($id, $module, FnsNotifyServiceInterface $notifyService, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->notifyService = $notifyService;
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['create']);
            unset($actions['index']);
            unset($actions['delete']);
            unset($actions['update']);
        }
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $element = new Notify();
        $data = $element->search(Yii::$app->request->getQueryParams());
        
        return $data;
    }
    
    /**
     * Возвращает список типов документов
     *
     * @return type
     */
    public function actionTypes()
    {
        return ['types' => array_map(function ($v, $k) {
            return ['id' => (string)$k, 'name' => $v];
        }, Notify::$types, array_keys(Notify::$types))];
    }
    
    /**
     * Возвращает список доступных макросов
     *
     * @return type
     */
    public function actionMacros()
    {
        return ['macros' => array_map(function ($v, $k) {
            return ['id' => $k, 'name' => $v];
        }, Notify::$macros, array_keys(Notify::$macros))];
    }
    
    /**
     * Проверка правил - Возвращает правило по которому сработает оповещение
     *
     * @param type $docid
     * @param type $type
     * @param type $object
     */
    public function actionCheck($docid = null, $type = null, $object = null, $state = null)
    {
        if (!empty($docid)) {
            $doc = Fns::findOne(['id' => $docid]);
            if (empty($doc)) {
                throw new BadRequestHttpException('Неверный номер документа');
            }
        }
        if (empty($doc)) {
            if (empty($type)) {
                throw new BadRequestHttpException('Вы должны указать или идентификатор документа или Тип документа');
            }
            
            $doc = new Fns();
            if ($state == 'success') {
                $doc->state = Fns::STATE_RESPONCE_SUCCESS;
            } else {
                $doc->state = Fns::STATE_RESPONCE_ERROR;
            }
            $doc->fnsid = $type;
            $doc->object_uid = $object;
        }
        AuditLog::Audit(AuditOperation::OP_INVOICE, 'Проверка оповещения', [['field' => 'Идентификатор документа', 'value' => $docid], ['field' => 'Тип документа', 'value' => $type], ['field' => 'Идентификатор объекта', 'value' => $object]]);
        
        return ['result' => $this->notifyService->check($doc)];
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'types':
            case 'check':
            case 'macros':
            case 'index':
            case 'view':
            case 'delete':
            case 'create':
            case 'update':
                if (!Yii::$app->user->can('report-fns-config')) {
                    throw new NotAcceptableHttpException('Запрет на выполнение операции');
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
