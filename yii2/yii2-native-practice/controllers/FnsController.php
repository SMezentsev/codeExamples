<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\FnsOcs;
use app\modules\itrack\models\FnsSort;
use app\modules\itrack\models\IsmLog;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use Yii;
use app\modules\itrack\models\AuditLog;
use app\modules\itrack\models\AuditOperation;
use app\modules\itrack\models\UsoCache;
use yii\db\Expression;
use app\modules\itrack\models\User;
use yii\web\HttpException;
use yii\web\NotAcceptableHttpException;
use app\modules\itrack\components\pghelper;

/**
 * @OA\Get(
 *  path="/fns",
 *  tags={"Документы МДЛП"},
 *  description="Получение списка документов МДЛП",
 *  @OA\Response(
 *      response="200",
 *      description="Документы МДЛП",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="fns",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Fns")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/fns/{id}",
 *  tags={"Документы МДЛП"},
 *  description="Получение документа МДЛП",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Документ МДЛП",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/fns/{id}",
 *  tags={"Документы МДЛП"},
 *  description="Изменение документа МДЛП",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Документ МДЛП",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/fns/{id}",
 *  tags={"Документы МДЛП"},
 *  description="Удаление документа МДЛП",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="204",
 *      description="",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *   path="/fns/{id}/params",
 *   tags={"Документы МДЛП"},
 *   description="Получение параметров документа МДЛП",
 *   @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Параметры документа МДЛП",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns_Params")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Post(
 *   path="/fns/{id}/params",
 *   tags={"Документы МДЛП"},
 *   description="Редактирование параметров документа МДЛП",
 *   @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *   ),
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns_Params")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Документ МДЛП",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Fns")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/api/v1/fns/download-ticket/{operationId}",
 *  tags={"Скачать тикет МДЛП"},
 *  description="Скачать тикет МДЛП",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор операции",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Тикет МДЛП",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class FnsController extends ActiveController
{
    use ControllerTrait;

    const LOG_LENGTH = 1000;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'fns',
    ];
    public $modelClass = 'app\modules\itrack\models\Fns';
    
    public function authExcept()
    {
        return ['download', 'update', 'import', 'import1c', 'import-tqs'];
    }
    
    public function actions()
    {
        $actions = parent::actions();
        
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['update']);
        unset($actions['create']);
        //unset($actions['delete']);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['upload']);
            unset($actions['update']);
            unset($actions['params']);
            unset($actions['download']);
            unset($actions['docResend']);
        }
        
        return $actions;
    }
    
    /*
     * Смена статуса в UCO-cache для повторной отправки запроса
     */
    public function actionCodeResend($id)
    {
        $codeid = \Yii::$app->request->getQueryParam('codeid');
        $uc = UsoCache::findOne(['id' => $codeid]);
        if (empty($uc)) {
            throw new BadRequestHttpException('Код не найден');
        }
        if ($uc->operation_uid != $id) {
            throw new BadRequestHttpException('Код не найден');
        }
        $uc->resend();
        AuditLog::Audit(AuditOperation::OP_FNS, "Повторная отправка запроса 210 в Марикровку по коду $uc->code", [['field' => "Код", 'value' => $uc->code]]);
        
        return ['message' => 'Запрос отправлен'];
    }
    
    /**
     * Переотправка дока в TQS(сброс статуса в 15)
     *
     * @param int $id
     *
     * @return array
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionDocResend($id)
    {
        $m = $this->modelClass;
        
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        if (!in_array($model->state, [Fns::STATE_TQS_COMPLETED, Fns::STATE_TQS_DECLAINED])) {
            throw new BadRequestHttpException('Нельзя изменять статус у текущего документа');
        }
        $model->state = Fns::STATE_TQS_RECEIVED;
        $model->save(false);
        AuditLog::Audit(AuditOperation::OP_FNS, 'Повторная отправка документа в TQS', [['field' => 'Документ', 'value' => $$id]]);
        
        return [];
    }
    
    /**
     * Отмена успешно принятых документов (мдлп-250)
     *
     * @param int $id
     *
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionDecline($id)
    {
        $m = $this->modelClass;
        
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        
        $model->decline();
        
        return [];
    }
    
    /**
     * Получение списка кодов, отправленных в УСО для уточнения
     *
     * @param int $id операции FNS
     *
     * @return ActiveDataProvider|array
     * @throws NotFoundHttpException
     */
    public function actionCodes($id)
    {
        $m = $this->modelClass;
        
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        AuditLog::Audit(AuditOperation::OP_FNS, "Просмотр кодов по 601 документу $model->id", [['field' => 'Идентификатор документа', 'value' => $model->id]]);
        
        $this->serializer = [
            'class'              => 'app\modules\itrack\components\boxy\Serializer',
            'collectionEnvelope' => 'codes',
        ];
        
        $dataProvider = new ActiveDataProvider(['query' => $model->getCache()]);
        
        if (in_array($model->operation_uid, [Fns::OPERATION_601, Fns::OPERATION_416])) {
            return $dataProvider;
        } else {
            return [];
        }
    }
    
    /**
     * Загрузка любого FNS документа через WEB
     *
     * @return array
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionUpload()
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotFoundHttpException('Загрузка возможна только на мастер сервере');
        }
        
        $modelClass = $this->modelClass;
        $force = (boolean)\Yii::$app->request->getBodyParam('force');
        //загрузка документов с веба
        //документ парсится по имени файла и заменяется действующий или же создается новый
        $doc = UploadedFile::getInstanceByName('file');
        
        if (empty($doc)) {
            throw new BadRequestHttpException('Ошибка загрузки файла');
        }
        if ($doc->extension != 'xml') {
            throw new BadRequestHttpException('Ошибка, некорректный формат файла');
        }
        $fnsid = "";
        if (preg_match('#^fns-(\d+)-.*$#si', $doc->baseName, $match)) {
            $fnsid = $match[1];
        }
        $fns = null;
        if (!empty($fnsid)) {
            $fns = $modelClass::findOne(['id' => $fnsid]);
        }
        
        if (empty($fns)) {
            //новый документ
            $fns = new $modelClass;
            $fns->state = Fns::STATE_READY;
            $fns->created_by = \Yii::$app->user->identity->id;
            $fns->operation_uid = Fns::OPERATION_210;
//            $fns->fns_start_send = new Expression('NOW()');
            $fns->is_uploaded = true;
            $fns->updateJustLoaded($doc->tempName);
            $after = \Yii::$app->request->getBodyParam('after');
            if (!empty($after)) {
                $after_fns = Fns::findOne($after);
            }
            if (!empty($after_fns) && !empty($after_fns->fns_start_send)) {
                $fns->fns_start_send = new Expression("'" . $after_fns->fns_start_send . "' + interval '1 milliseconds' ");
            } else {
                $fns->fns_start_send = new Expression("'" . $fns->created_at . ' ' . $fns->created_time . "'");
            }
            $fns->updated_at = new Expression("'" . $fns->created_at . ' ' . $fns->created_time . "'");
            $fns->uploaded_at = new Expression('now()');
            
            $fns->save(false);
            $fns->refresh();
            
            $doc->saveAs($fns->getFileName());
            AuditLog::Audit(AuditOperation::OP_FNS, 'Загрузка нового документа', [['field' => "Идентификатор документа", 'value' => $fns->id], ['field' => 'Тип документа', 'value' => $fns->fnsid]]);
        } else {
            $fns = $modelClass::findOne(['id' => $fnsid]);
            if (!in_array($fns->state, [Fns::STATE_RESPONCE_PART, Fns::STATE_ERRORSTOPED, Fns::STATE_RESPONCE_ERROR, Fns::STATE_SEND_ERROR, Fns::STATE_STOPED, Fns::STATE_READY, Fns::STATE_CHECKING])) {
                throw new BadRequestHttpException('Данный документ не может быть перезагружен');
            }
            
            //меняем существующий
            if ($force) {
                $old_d = $fns->created_at;
                $old_t = $fns->created_time;
                $doc->saveAs($fns->getFileName());
                $fns->updateJustLoaded($fns->getFileName());
                $fns->updated_at = new Expression("'" . $fns->created_at . ' ' . $fns->created_time . "'");
                $fns->upd = true;
                $fns->created_at = $old_d;
                $fns->created_time = $old_t;
                
                $fns->state = Fns::STATE_READY;
                $fns->is_uploaded = true;
                $fns->replaced = true;
                $fns->uploaded_at = new Expression('now()');
                $fns->prev_uid = null;
                $fns->save(false);
                $fns->refresh();
                AuditLog::Audit(AuditOperation::OP_FNS, "Загрузка документа с заменой существующего $fns->id", [['field' => "Идентификатор документа", 'value' => $fns->id], ['field' => "Тип документа", 'value' => $fns->fnsid]]);
            } else {
                throw new HttpException(400, 'Заменить существующий документ?', 5001);
            }
        }
        //уменьшаем объем отдаваемого, убираем большие массивы
        $fns->codes = null;
        $fns->full_codes = null;
        $fns->fns_log = null;
        
        return ["fns" => $fns];
    }
    
    
    /**
     * импорт документов от TQS
     *
     * @return array
     */
    public function actionImportTqs()
    {
        try {
            $rootUser = User::findByLogin(User::SYSTEM_USER);
            if (empty($rootUser)) {
                throw new HttpException(400, 'User roor not found');
            }
            $data = \Yii::$app->request->getBodyParam('data');
            if (is_array($data) || is_object($data)) {
                $body = json_encode($data);
            } else {
                $body = trim($data);
            }
            
            //не понятный логин, пока просто сохраняем
            $userid = trim(\Yii::$app->request->getBodyParam('id'));
            
            $result = FnsOcs::createTQSinput([
                'created_by' => $rootUser->id,
                'body'       => $body,
                'userid'     => $userid,
            ]);
        } catch (\Exception $ex) {
            return ['status' => 200, 'message' => $ex->getMessage()];
        }
        
        return $result;
    }
    
    /**
     * импорт входящих документов от 1С
     *
     * @return array
     * @throws HttpException
     */
    public function actionImport1c()
    {
        return false;
        $body = trim(\Yii::$app->request->getRawBody());
        Fns::create1Cinput([
            'created_by' => 0,
            'body'       => $body,
        ]);
       
        return ['status' => 200, 'message' => 'Ok'];
    }
    
    public function afterSerializeModels($data)
    {
        foreach ($data as &$item) {
            unset($item['codes'], $item['codes_data'], $item['full_codes'], $item['fns_log']);
        }
        
        return $data;
    }
    
    /**
     * Подготовка данных для вывода в actionIndex (actions['index'])
     *
     * @return ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $this->serializer['afterSerializeModels'] = [$this, 'afterSerializeModels'];
        
        $params = \Yii::$app->request->getQueryParams();
        if (isset($params["operation_uid"])) {
            if (!\Yii::$app->user->can('report-fns-' . $params["operation_uid"]) && $params["operation_uid"] < 200)   //фича на операции более 200 - не проверяем права
            {
                throw new NotAcceptableHttpException('Запрет на выполнение операции');
            }
        } else {
            if (!\Yii::$app->user->can('report-fns-upload'))   //фича на операции более 200 - не проверяем права
            {
                throw new NotAcceptableHttpException("Запрет на выполнение операции");
            }
        }
        if (!\Yii::$app->user->can('see-all-objects')) {
            $params["object_uid"] = \Yii::$app->user->getIdentity()->object_uid;
        }
        
        //AuditLog::Audit(AuditOperation::OP_FNS, "Просмотр списка документов", [['field'=>"Идентификатор типа документа",'value'=>isset($params["operation_uid"])?$params["operation_uid"]:""]]);
        
        $sort = new FnsSort();
        $dataProvider = $sort->search($params);
        
        return $dataProvider;
    }
    
    /*
     * Возврат в фрон списка статусов в зависимости от раздела документов (TQS|1C|ФНС)
     */
    public function actionStatuses($type = "fns")
    {
        return ["statuses" => Fns::statuses($type)];
    }
    
    /*
     * Получение входящих документов от ФНС
     */
    public function actionImport($body = null)
    {
        if ($body) {
            $request = $body;
        } else {
            $request = \Yii::$app->request->getRawBody();
        }
        
        $fns = Fns::createImport($request);
        
        return ["status" => 200];
    }

    /**
     * Запросов логов отправки по идентификатор документа
     * логи получаем от коннектора и отдаем "как есть"
     *
     * @param integer $id идентификатор документа
     *
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionLogs($id)
    {
        $m = $this->modelClass;
        
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        
        $logs = IsmLog::find()->where(['operation_id' => $id])->orderBy(['created' => SORT_ASC])->all();
        $data = [];
        /** @var IsmLog $log */
        foreach ($logs as $log) {
            $link = '';
            $body = $log->body;
            if (mb_strlen($body) > self::LOG_LENGTH) {
                $link = \Yii::$app->urlManager->createAbsoluteUrl(['fns/download-log', 'id' => $log->id, 'access-token' => Yii::$app->user->getIdentity()->getToken()]);
                $body = mb_substr($body, 0, self::LOG_LENGTH);
            }

            $data[] = [
                'created_at' => $log->created,
                'id'         => $log->operation_id,
                'xml'        => $body,
                'link'       => $link,
                'status'     => IsmLog::getLogType($log->log_type),
            ];
        }
        AuditLog::Audit(AuditOperation::OP_FNS, "Запрос логов по документу $model->id", [['field' => 'Идентификатор документа', 'value' => $model->id]]);
        
        return ['fns' => $data];
    }

    /**
     * Скачать тикет
     *
     * @param integer $operationId
     *
     * @return Response
     * @throws \Exception
     */
    public function actionDownloadTicket($operationId)
    {
        if ($fns = Fns::findOne(['operation_uid' => $operationId])) {
            return \Yii::$app->response->sendContentAsFile($fns->fns_log, '/ticket_' . $operationId . '.xml');
        }
        throw new NotFoundHttpException(sprintf('Операция #%s не найдена', (string)$operationId));
    }

    /**
     * Скачать файл истории
     *
     * @param integer $id
     *
     * @return Response
     * @throws \Exception
     */
    public function actionDownloadLog($id)
    {
        if ($log = IsmLog::findOne(['id' => $id])) {
            $ext = '.xml';
            if (strrpos($log->body, '<?xml') === false) {
                $ext = '.txt';
            }

            return \Yii::$app->response->sendContentAsFile($log->body, '/history_' . $id . $ext);
        }

        throw new NotFoundHttpException(sprintf('История #%s не найдена', (string)$id));
    }
    
    /*
     * Запрос на скачинвание XML документа + фича по автоматической генерации 210 дока по uso_cache
     * 
     * @params integer $id идентфиикатор документа
     */
    public function actionDownload($id)
    {
        $m = $this->modelClass;
        $type = \Yii::$app->request->getQueryParam('type');
        $tok = \Yii::$app->request->getQueryParam('tok');
        
        if ($type == 210) {
            $u = UsoCache::find()->andWhere(['id' => $id])->one();
            
            if (!$u) {
                throw new NotFoundHttpException("Can't find fns doc: {$id}");
            }
            if (md5($u->cdate . $u->id) != $tok) {
                throw new BadRequestHttpException('Ошибка, доступа к файлу');
            }
            $model = new Fns();
            $model->load([
                'operation_uid' => Fns::OPERATION_210,
                'state'         => Fns::STATE_CREATED,
                'fnsid'         => $type,
                'code'          => $u->code,
                'fns_params'    => serialize(["codetype_uid" => $u->codetype_uid]),
                'internal'      => true,
                'object_uid'    => $u->object_uid,
            ], '');
        } else {
            if ($type == 'ocs') {
                $m .= 'Ocs';
            }
            $model = $m::find()->where(['id' => $id])->one();
            if (!$model) {
                throw new NotFoundHttpException("Can't find fns doc: {$id}");
            }
            if (md5($model->created_at . $model->created_time . $model->id) != $tok) {
                throw new BadRequestHttpException('Ошибка, доступа к файлу');
            }
        }
        
        
        if ($model->state >= Fns::STATE_CREATED) {
            $fname = $model->getFileName();
            //фигня... если док был сгененрирован когда не был известен fnsid, то этого поля в имени не было TODO в update_xxx - переименовать файл на имя с fnsid
            if (!file_exists($fname)) {
                $fname = preg_replace('#^(.*?)\-(\d+)\.xml$#', '$1-.xml', $fname);
            }
            if (file_exists($fname) && $type != 210 && !$model->regen) {
                $xml = file_get_contents($fname);
            } else {
                $xml = $model->xml();
                if ($model->regen) //сбрасываем флаг - что есть необходимость перегенерации файла
                {
                    $model->regen = false;
                    $model->save();
                }
                if (\Yii::$app->request->getQueryParam('save') !== 'only') {
                    file_put_contents($model->getFileName(), $xml);
                }
            }
            $xml = preg_replace('#^<\?xml version="[\d\.]+"\?>\s*#si', '', $xml);
            \Yii::$app->getResponse()->sendContentAsFile($xml, $model->getFileName(false));
            AuditLog::Audit(AuditOperation::OP_FNS, "Сохранение документа $model->id", [['field' => "Идентификатор документа", 'value' => $model->id]]);
        } else {
            throw new BadRequestHttpException('Ошибка, нельзя получить данный файл');
        }
    }
    
    /**
     * Запрос/сохранение параметров для документа с заданным ID - для редактирования в фронте
     *
     * @param integer $id идентификатор ФНС документа
     *
     * @return array c Fns моделью
     * @throws NotFoundHttpException
     * @throws NotAcceptableHttpException
     * @throws BadRequestHttpException
     */
    public function actionParams($id)
    {
        $m = $this->modelClass;
        
        $transaction = Yii::$app->db->beginTransaction();
        /** @var Fns $model */
        $model = $m::findOneForUpdate($id);
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        
        if (!\Yii::$app->user->can('report-fns-' . (($model->operation_uid == 34) ? 14 : ((in_array($model->operation_uid, [36, 40, 35]) ? 57 : $model->operation_uid))))) {
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        
        if (\Yii::$app->request->isGet) {
            $params = $model->getParams();
            if (empty($params)) {
                $params = $model->createParams();
            }
            if (!isset($params['operation_date'])) {
                $params['operation_date'] = $model->cdt;
            }
            if (isset($params['operation_date'])) {
                $params['operation_date'] = preg_replace('#:00$#', '', preg_replace('#T#', ' ', $params['operation_date']));
            }
            if (isset($params['doc_date'])) {
                if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', $params['doc_date'], $m)) {
                    $params['doc_date'] = $m[3] . '-' . $m[2] . '-' . $m[1];
                }
                $params['doc_date'] = preg_replace('#:00$#', '', preg_replace('#T#', ' ', $params['doc_date']));
            }
            if (isset($params['saveOnly'])) {
                unset($params['saveOnly']);
            }
            if (isset($params['access-token'])) {
                unset($params['access-token']);
            }
            if (!isset($params['object_uid'])) {
                $params['object_uid'] = $model->object_uid;
            }
            if (!isset($params['newobject_uid'])) {
                $params['newobject_uid'] = $model->newobject_uid;
            }
            
            return ['params' => $params];
        } else {
            if ($model->getCanParams()) {
                $oldparams = $model->getParams();
                if (empty($oldparams)) {
                    $oldparams = $model->createParams();
                }
                
                
                $params = \Yii::$app->request->getBodyParams();
                $au = [];
                $au[] = ['field' => 'Идентфикатор документа', 'value' => $model->id];
                foreach ($params as $k => $v) {
                    if (!empty($v) && !AuditLog::canSkip($k)) {
                        $au[] = ['field' => AuditLog::trans($k), 'value' => isset($oldparams[$k]) ? $oldparams[$k] : '', 'new' => $v];
                    }
                }
                
                AuditLog::Audit(AuditOperation::OP_FNS, "Сохранение параметров документа $model->id", $au);
                
                if (isset($params['access-token'])) {
                    unset($params['access-token']);
                }
                if (isset($params['saveOnly']) && $params['saveOnly']) {
                    $model->state = Fns::STATE_CHECKING;
                } else {
                    $model->state = Fns::STATE_READY;
                }
                //у 313 доков при сохранении или отправке запоминаем коды - чтобы док больше не перестраивался... кроме параметров...
                if ($model->operation_uid == Fns::OPERATION_EMISSION_ID) {
                    $model->saveCodes313();
                    if ($model->updated_at > $params['operation_date']) {
                        $params['operation_date'] = $model->updated_at;
                    }
                }
                unset($params['saveOnly']);
                $model->fns_params = serialize($params);
                //фича, если регистрация или передача на уничтожение - нам нужен номер дока в гриде веба, заьерем это значение из парамсов и соханим в fdata
                if ($model->operation_uid == Fns::OPERATION_DESTRUCTION_ID || $model->operation_uid == Fns::OPERATION_DESTRUCTIONACT_ID) {
                    $model->data = pghelper::arr2pgarr(['doc_num', $params['doc_num']]);
                }
                $model->updated_at = $params['operation_date'];
                $model->upd = true;
                
                if ($model->fnsid == '552') {
                    $d = pghelper::pgarr2arr($model->data);
                    $d[1] = $params['doc_num'] ?? '';
                    $d[2] = $params['doc_date'] ?? '';
                    $model->data = pghelper::arr2pgarr($d);
                }
                
                //если нам прислали inn + kpp и у текущего дока fnsid 415, то надо док перевести в 441и инн с кпп сохранить в накладной данного дока
                if (isset($params['inn']) && isset($params['kpp']) && !empty($params['inn']) && !empty($params['kpp']) && $model->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID) {
                    //стираем старый документ и генерим новый
                    @unlink($model->getFileName());
                    
                    $model->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
                    $model->fnsid = '441';
                    $model->save(false);
                    $model->invoice->dest_inn = $params['inn'];
                    $model->invoice->dest_kpp = $params['kpp'];
                    
                    file_put_contents($model->getFileName(), $model->xml());
                    //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                    @chmod($model->getFileName(), 0666);
                }
                //441 док и нам не прислали inn + kpp
                if ($model->operation_uid == Fns::OPERATION_OUTCOMERETAILUNREG_ID && isset($params['receiver_id']) && !empty($params['receiver_id'])) {
                    //стираем старый документ и генерим новый
                    @unlink($model->getFileName());
                    
                    $model->operation_uid = Fns::OPERATION_OUTCOMERETAIL_ID;
                    $model->fnsid = '415';
                    $model->save(false);
                    $model->invoice->dest_fns = $params['receiver_id'];
                    
                    file_put_contents($model->getFileName(), $model->xml());
                    //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                    @chmod($model->getFileName(), 0666);
                }
                //фича 2 для 415 и 441 по сотексу
                if (in_array($model->operation_uid, [Fns::OPERATION_OUTCOMERETAILUNREG_ID, Fns::OPERATION_OUTCOMERETAIL_ID]) && isset($params['manufacturer']['uid']) && !empty($params['manufacturer']['uid'])) {
                    //у нас отгрузка и веб прислал ссылку на производителя
                    $man = \app\modules\itrack\models\Manufacturer::findOne($params['manufacturer']['uid']);
                    if (!empty($man)) {
                        //нашли производителя и взависимости от того какие данные у него есть.. такой док и делаем
                        //стираем старый документ и генерим новый
                        @unlink($model->getFileName());
                        if (!empty($man->fnsid)) {
                            //415 с fnsid
                            unset($params['inn'], $params['kpp'], $params['regNum']);
                            $params['receiver_id'] = $man->fnsid;
                            
                            $model->operation_uid = Fns::OPERATION_OUTCOMERETAIL_ID;
                            $model->fnsid = '415';
                            $model->save(false);
                            $model->invoice->dest_fns = $params['receiver_id'];
                        } elseif (!empty($man->ownerid)) {
                            //441 с regNum
                            unset($params['inn'], $params['kpp'], $params['receiver_id']);
                            $params['regNum'] = $man->ownerid;
                            
                            $model->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
                            $model->fnsid = '441';
                            $model->save(false);
                            $model->invoice->dest_inn = $params['inn'];
                            $model->invoice->dest_kpp = $params['kpp'];
                        } else {
                            //441 с inn
                            unset($params['receiver_id'], $params['regNum']);
                            $params['inn'] = $man->inn;
                            $params['kpp'] = $man->kpp;
                            
                            $model->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
                            $model->fnsid = '441';
                            $model->save(false);
                            $model->invoice->dest_inn = $params['inn'];
                            $model->invoice->dest_kpp = $params['kpp'];
                        }
                        $model->fns_params = serialize($params);
                        file_put_contents($model->getFileName(), $model->xml($params));
                        //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                        @chmod($model->getFileName(), 0666);
                    }
                }
                
                $model->save(false);
                $transaction->commit();
                
                @unlink($model->getFileName());
                //генерируем документ
                $attachmentName = 'fns-' . $model->id . '.xml';
                if ($model->state == Fns::STATE_READY) {
                    $xml = $model->xml();
                    file_put_contents($model->getFileName(), $xml);
                }
            } else {
                throw new BadRequestHttpException('Запрет на изменение параметров');
            }
        }
        
        return ['fns' => $model];
    }
    
    /**
     * Получение от коннектора статуса отправки документа в УСО/TQS/1c
     *
     * @param integer $id ид документа
     *
     * @return Fns модель документа
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionUpdate($id)
    {
        $m = $this->modelClass;
        $type = \Yii::$app->request->getQueryParam('type');
        if ($type == 'ocs') {
            $m .= 'Ocs';
        }
        
        /** @var \app\modules\itrack\models\Fns $model */
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find fns doc: {$id}");
        }
        
        return $model->answer(array_merge(\Yii::$app->request->getQueryParams(), \Yii::$app->request->getBodyParams()));
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        $message = 'Запрет на выполнение операции';
        switch ($action) {
            case 'delete':
                if (!\Yii::$app->user->can('report-fns-delete')) {
                    throw new NotAcceptableHttpException($message);
                }
                break;
            case 'upload':
                if (!\Yii::$app->user->can('report-fns-upload')) {
                    throw new NotAcceptableHttpException($message);
                }
                break;
            case 'docResend':
            case 'doc-resend':
                if (!\Yii::$app->user->can('report-fns-upload')) {
                    throw new NotAcceptableHttpException($message);
                }
                break;
            case 'download-ticket':
                if (!\Yii::$app->user->can('report-fns-download-ticket')) {
                    throw new NotAcceptableHttpException($message);
                }
                break;
            case 'params':
            case 'index':
//                if (!\Yii::$app->user->can('report-fns'))
//                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}
