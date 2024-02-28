<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 4:19
 */

namespace app\modules\itrack\controllers;


use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\events\erp\santens\OrderCompleteEvent;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\erp\ErpOrdersConductor;
use app\modules\itrack\models\erp\ErpOrdersManager;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationSort;
use app\modules\itrack\models\GenerationStatus;
use yii\base\Event;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use Zebra\Client;
use Zebra\Zpl\Builder;
use Zebra\Zpl\GdDecoder;
use Zebra\Zpl\Image;

/**
 * @OA\Post(
 *   path="/generations",
 *   tags={"Генерация кодов"},
 *   description="Создание генерации кодов",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Генерация кодов",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/generations",
 *  tags={"Генерация кодов"},
 *  description="Получение списка генераций кодов",
 *  @OA\Response(
 *      response="200",
 *      description="Генерации кодов",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="generations",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/generations/{id}/download",
 *  tags={"Генерация кодов"},
 *  description="Скачивание файла с кодами",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Коды",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/generations/{id}",
 *  tags={"Номенклатуры"},
 *  description="Удаление номенклатуры",
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
class GenerationController extends ActiveController
{
    use ControllerTrait;
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'generations',
    ];
    
    public $modelClass = 'app\modules\itrack\models\Generation';
    
    public function authExcept()
    {
        return ['print2', 'print3'];
    }
    
    public function actions()
    {
        $actions = parent::actions();
        
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        // Переопределение сценария для группового кода
        if (CodeType::CODE_TYPE_GROUP == \Yii::$app->getRequest()->getBodyParam('codetype_uid')) {
            $actions['create']['scenario'] = 'groupCode';
        }
        
        unset($actions['delete']);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
//            $actions['create']['modelClass'] = 'app\modules\sklad\models\cache\Generation';
//            $this->modelClass = 'app\modules\sklad\models\Generation';
            unset($actions['update']);
        }
        
        return $actions;
    }
    
    /**
     * Подготовка данных для вывода в actionIndex (actions['index'])
     *
     * @return ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $params = \Yii::$app->request->getQueryParams();
        
        $sort = new GenerationSort();
        $dataProvider = $sort->search($params);
        $dataProvider->query->with('codeType');
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'generations.object_uid', \Yii::$app->user->identity->object_uid]);
        }
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Просмотр списка заказа кодов", []);
        return $dataProvider;
    }
    
    /**
     * устаревшее - давно нет загрузки
     *
     * @return type
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionUpload()
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotFoundHttpException('Загрузка возможна только на мастер сервере');
        }
        
        $modelClass = $this->modelClass;
        $doc = UploadedFile::getInstanceByName('file');
        
        if (empty($doc)) {
            throw new BadRequestHttpException('Ошибка загрузки файла');
        }
        if ($doc->extension != 'csv') {
            throw new BadRequestHttpException('Ошибка, некорректный формат файла (csv)');
        }
        $codes = @file($doc->tempName);
        
        $gen = new $modelClass;
        $gen->scenario = "default";
        $params = \Yii::$app->request->getBodyParams();
        $params["cnt"] = count($codes);
        $params["codetype_uid"] = CodeType::CODE_TYPE_INDIVIDUAL;
        if (!isset($params["product_uid"]) || empty($params["product_uid"])) {
            throw new BadRequestHttpException('Ошибка, незадана товарная карта');
        }
        
        $gen->load($params, '');
        $gen->save();
        $gen->refresh();
        $gen->status_uid = \app\modules\itrack\models\GenerationStatus::STATUS_READY;
        $gen->save();
        foreach ($codes as $str) {
            $str = trim($str);
            if (!empty($str)) {
                \Yii::$app->db->createCommand("INSERT INTO codes_external (code,product_uid,object_uid,generation_uid)
                                                            VALUES (:code,:product_uid,:object_uid,:generation_uid)", [
                    ":code"           => $str,
                    ":product_uid"    => $gen->product_uid,
                    ":object_uid"     => $gen->object_uid,
                    ":generation_uid" => $gen->id,
                ])->execute();
            }
        }
        
        return ["generations" => $gen];
    }
    
    /**
     * Изменения статуса сессии привязанной к данному заказу - для продолжения опрса оборудования
     *
     * @param uuid       $id     - ид заказа
     * @param TqsSession $tqs    - ид сессии
     * @param string     $action - 'run'/'pause'
     *
     * @return array
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionTqs($id, $tqs, $action)
    {
        $mc = $this->modelClass;
        $model = $mc::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find generation: {$id}");
        }
        if (!in_array($model->status_uid, [GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT])) {
            throw new BadRequestHttpException('Нельзя изменить статус сессии');
        }
        
        $ts = \app\modules\itrack\models\TqsSession::findOne($tqs);
        if (empty($ts)) {
            throw new BadRequestHttpException('Сессия не найдена');
        }
        
        
        if ($action == 'run') {
            $ts->state = 0;
        } else {
            $ts->state = -1;
        }
        
        if (!$ts->save(false)) {
            throw new BadRequestHttpException('Ошибка изменения статуса сессии');
        }
        
        return ["status" => 'Ok'];
    }
    
    /**
     * Скачивание сгенерированного файла
     *
     * @param $id
     *
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDownload($id)
    {
        /** @var Generation $model */
//        $model = $this->findModel($id);
        
        /** @var Generation $m */
        $m = $this->modelClass;
        $type = \Yii::$app->request->getQueryParam('type');
        
        $model = $m::find()->where(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException("Can't find generation: {$id}");
        }

        if($type == 'OCS') {
            $fileName = $model->getFileNameOCS();
        } elseif ($type == 'THIRD_PARTY') {
            $filePath = $model->getPalletCodesFilePath();

            if (!is_file($filePath)) {
                throw new NotFoundHttpException("Не могу найти файл генерации");
            }

            return \Yii::$app->getResponse()->sendFile($filePath)->send();
        } else {
            $fileName = $model->getFileName();
        }

        $attachmentName = null;
        
        if (!in_array($model->status_uid, [GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_READY, GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_DECLINED, GenerationStatus::STATUS_TIMEOUT]) || !file_exists($fileName)) {
            \Yii::info("Generation file not found: $fileName");
            throw new NotFoundHttpException("Не могу найти файл генерации");
        } else {
            if ($model->codetype_uid == CodeType::CODE_TYPE_GROUP) {
                if ($model->save_cnt > 0) {
                    throw new NotFoundHttpException("Первышен лимит скачиваний групповых кодов.");
                }
                $fn = explode(DIRECTORY_SEPARATOR, $fileName);
                if (false !== strpos($fn[sizeof($fn) - 1], '.csv.gz')) {
                    $attachmentName = str_replace('.csv.gz', '-' . \Yii::$app->formatter->asDatetime($model->created_at, 'ddMMyy-HHmm') . '-' . $model->cnt . '.csv.gz', $fn[sizeof($fn) - 1]);
                } else {
                    $attachmentName = str_replace('.csv', '-' . \Yii::$app->formatter->asDatetime($model->created_at, 'ddMMyy-HHmm') . '-' . $model->cnt . '.csv', $fn[sizeof($fn) - 1]);
                }
            }
            
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Сохранение файла с заказом кодов ($model->id)", [["field" => "Идентификатор заказа", "value" => $model->id], ["field" => "Номер заказа", "value" => $model->object_uid . "/" . $model->num]]);
            \Yii::$app->db->createCommand("SELECT mark_gen_saved(:ID)", [":ID" => $model->id])->execute();
            \Yii::$app->getResponse()->sendFile($fileName, $attachmentName);
        }
    }
    
    public function actionCreateOrder()
    {
        $error = '';
        $orderId = null;
        
        $erpManager = \Yii::createObject(ErpOrdersManager::class);
        $erpManager->setData(\Yii::$app->request->post());
        try {
            $orderId = $erpManager->create();
        } catch (\ErrorException $e) {
            \Yii::$app->response->statusCode = 422;
            $error = $e->getMessage();
        }
        
        return [
            'state'   => ($error === '') ? true : false,
            'payload' => ($orderId !== null) ? $orderId : '',
            'error'   => ($error === '') ? '' : $error,
        ];
    }
    
    public function actionOrderStatus()
    {
        $error = '';
        $data = null;
        
        $erpConductor = \Yii::createObject(ErpOrdersConductor::class);
        $signs = (\Yii::$app->request->get('signs') !== null) ? \Yii::$app->request->get('signs') : 'true';
        try {
            $data = $erpConductor->info(\Yii::$app->request->get('order_id'), $signs);
        } catch (\ErrorException $e) {
            \Yii::$app->response->statusCode = 422;
            $error = $e->getMessage();
        }
        
        return [
            'state'   => ($error == '') ? true : false,
            'payload' => ($data !== null) ? $data : '',
            'error'   => ($error === '') ? '' : $error,
        ];
    }
    
    /**
     * Вывод кодов для печати
     *
     * @param $id
     *
     * @return ActiveDataProvider
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionPrint($id)
    {
        /** @var Generation $model */
        $model = $this->findModel($id);
        
        if (!in_array($model->status_uid, [
            GenerationStatus::STATUS_READY,
            GenerationStatus::STATUS_CONFIRMED,
            GenerationStatus::STATUS_DECLINED,
            GenerationStatus::STATUS_TIMEOUT,
            GenerationStatus::STATUS_CONFIRMEDWOADDON,
            GenerationStatus::STATUS_CONFIRMEDREPORT,
        ])) {
            throw new NotFoundHttpException("Not found by id:" . $id);
        }
        
        $_GET['fields'] = 'uid,code';
        $_GET['expand'] = 'dataMatrixUrl';
        $this->serializer['collectionEnvelope'] = 'codes';
        
        $generationId = $id;
        
        $sql = "
                    SELECT getcodes.*, product.cdate, product.expdate, product.series, nomenclature.gtin,objects.gs1,product.expdate_full as expdate_gs1, nomenclature.tnved, objects.external
                        FROM (
                          SELECT * FROM _get_codes(:where)
                        ) getcodes
                        LEFT JOIN generations ON generations.id = getcodes.generation_uid
                        LEFT JOIN objects ON objects.id = generations.object_uid
                        LEFT JOIN product on (product.id = getcodes.product_uid)
                        LEFT JOIN nomenclature on (product.nomenclature_uid = nomenclature.id)
                        ORDER by id
        ";
        $sql = str_replace(':where', "'generation_uid=''{$generationId}'''", $sql);
        
        $pages = new Pagination([
            'totalCount'      => $model->cnt,
            'pageSizeLimit'   => [1, 1000],
            'defaultPageSize' => 100,
        ]);
        
        $codes = \Yii::$app->db->createCommand($sql)->getRawSql();
        
        $this->serializer['afterSerializeModels'] = [$this, 'afterSerializeModels'];
        
        \Yii::$app->params['generation'] = $model;
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Создание списка кодов для печати ($model->id)", [["field" => "Идентификатор заказа", "value" => $model->id], ["field" => "Номер заказа", "value" => $model->object_uid . "/" . $model->num]]);
        
        return new SqlDataProvider([
            'sql'        => $codes,
            'pagination' => $pages,
            'totalCount' => $model->cnt,
        ]);
    }
    
    public function actionPrint2($id)
    {
        $this->layout = false;
        $dataProvider = $this->actionPrint($id);
        if ($dataProvider instanceof SqlDataProvider) {
            $models = $dataProvider->getModels();
            $models = $this->afterSerializeModels($models);
            $dataProvider->setModels($models);
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Просмотр кодов в браузере для печати ($id)", [["field" => "Идентификатор заказа", "value" => $id]]);
        
        \Yii::$app->response->format = Response::FORMAT_HTML;
        echo $this->render('print2', [
            'dataProvider' => $dataProvider,
            'generation'   => $this->findModel($id),
        ]);
        die;
    }
    
    public function actionPrint3($id)
    {
        $this->layout = false;
        $dataProvider = $this->actionPrint($id);
        if ($dataProvider instanceof SqlDataProvider) {
            $models = $dataProvider->getModels();
            $models = $this->afterSerializeModels($models);
            $dataProvider->setModels($models);
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Просмотр кодов в браузере для печати ($id)", [["field" => "Идентификатор заказа", "value" => $id]]);
        
        \Yii::$app->response->format = Response::FORMAT_HTML;
        echo $this->render('print3', [
            'dataProvider' => $dataProvider,
            'generation'   => $this->findModel($id),
        ]);
        die;
    }
    
    public function actionPrintZebra($id)
    {
        $dataProvider = $this->actionPrint($id);
        if ($dataProvider instanceof SqlDataProvider) {
            $models = $dataProvider->getModels();
            $models = $this->afterSerializeModels($models);
            $dataProvider->setModels($models);
        }
        
        /** @var Generation $model */
        $model = $this->findModel($id);
        $template = "";
        
        $zebraId = \Yii::$app->request->getQueryParam('zebraId');
        if (!empty($zebraId)) {
            $zebra = \app\modules\itrack\models\Equip::find()->andWhere(['id' => $zebraId])->one();
            if (!empty($zebra)) {
                $ip = $zebra->ip;
                $template = $zebra->zpl;
            }
        } else {
            $ip = \app\modules\itrack\models\Equip::getZebraIp($model->object_uid);
        }
        if (empty($ip)) {
            throw new BadRequestHttpException('Не задан принтер');
        }
        $filename = \Yii::getAlias('@lockPath') . "/" . \Yii::$app->user->identity->id . "_barcode.png";
        
        $cnt = 0;
        foreach ($dataProvider->getModels() as $model) {
            $zpl = "";
            if (!empty($template)) {
                $cnt++;
                $zpl = $template;
                $zpl = str_replace('%CODE%', $model["code"], $zpl);
                $zpl = str_replace('%SEQ_NUMBER%', $cnt, $zpl);
                $zpl = str_replace('{{CODE}}', $model["code"], $zpl);
                $zpl = str_replace('{{SEQ_NUMBER}}', $cnt, $zpl);
            } else {
                $model = (!is_array($model)) ? $model->toArray([]) : $model;
                $builder = new \Ayeo\Barcode\Builder();
                $builder->setBarcodeType('gs1-128');
                $builder->setWidth(400);
                $builder->setHeight(120);
                $builder->setFilename($filename);
                $builder->saveImage('(00)' . $model["code"]);
                
                $decoder = GdDecoder::fromPath($filename);
                //$decoder = GdDecoder::fromString($filename);
                $image = new Image($decoder);
                
                $zpl = new Builder();
                
                foreach (\Yii::$app->params["zebra"]["zpl"] as $str) {
                    if ($str["type"] == "code") {
                        $zpl->fo($str["x"], $str["y"])->gf($image)->fs();
                    }
                    if ($str["type"] == "textcode") {
                        $zpl->cf(0, $str["size"])->fo($str["x"], $str["y"])->fd($model["code"])->fs();
                    }
                    if ($str["type"] == "text") {
                        $zpl->cf(0, $str["size"])->fo($str["x"], $str["y"])->fh()->fd($str["data"])->fs();
                    }
                }
            }
            $client = new Client($ip);
            $client->send($zpl);
            @unlink($filename);
            sleep(1);
            unset($client, $builder, $image, $zpl);
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Печать кодов на принтере ZEBRA ($id)", [["field" => "Идентификатор заказа", "value" => $id]]);
        
        return ['printed' => $dataProvider->count];
    }
    
    public function afterSerializeModels($data)
    {
        /** @var Generation $generation */
        $generation = \Yii::$app->params['generation'];
        
        function getCheckUrl($code)
        {
            $codeGenerationParams = \Yii::$app->params['codeGeneration'];
            $codeGenerationParamsUrl = $codeGenerationParams['codeCheckUrl'];
            
            return str_replace('{code}', $code, $codeGenerationParamsUrl);
        }
        
        $getDataMatrixUrl = function ($codeTypeId, $code) {
            $url = \Yii::$app->urlManager->baseUrl . '/' . \Yii::$app->params['dataMatrixFile'];
            
            if ($codeTypeId == CodeType::CODE_TYPE_GROUP) {
                if (!empty($code["gs1"]) || $code["external"]) {
                    $url = \Yii::$app->urlManager->baseUrl . '/barcode' . '?code=' . base64_encode($code["code"]);
//                    $url .= '?code=' . base64_encode(chr(29) . $code['code']) . '&type=code128';
                } else {
                    $url .= '?code=' . base64_encode($code['code']) . '&type=EAN13';
                }
            } else {
                $ch = \app\modules\itrack\components\pghelper::pgarr2arr($code["childrens"]);
                
                if (!empty($ch[0])) {
                    $crypto = explode('~', $ch[0], 2);
                    $code["crypto91"] = substr($crypto[0], 2);
                    $code["crypto92"] = substr($crypto[1], 2);
                    unset($code["expdate_gs1"], $code["series"]);
                } else {
                    list($d, $m, $y) = explode(' ', $code["expdate_gs1"]);
                    $code["expdate_gs1"] = substr($y, 2) . $m . $d;
                }
                //$gs1 = str_replace(chr(29), chr(232), \app\commands\GenerationController::genGS1v20170125($code,false));
                $gs1 = \app\commands\GenerationController::genGS1v20170125($code, false);
                //$gs1 = \app\commands\GenerationController::genGS1v20170125($code);
                $url .= '?s=1&code=' . base64_encode($gs1);
            }
            
            return $url;
        };
        
        foreach ($data as &$item) {
            $item['dataMatrixUrl'] = $getDataMatrixUrl($generation->codetype_uid, $item);
        }
        
        return $data;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
                if (CodeType::CODE_TYPE_GROUP == \Yii::$app->getRequest()->getBodyParam('codetype_uid') && !\Yii::$app->user->can('generation-create-group')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                if (CodeType::CODE_TYPE_INDIVIDUAL == \Yii::$app->getRequest()->getBodyParam('codetype_uid') && !\Yii::$app->user->can('generation-create-individual')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete':
            case 'update':
                throw new NotAcceptableHttpException("Запрет на выполнение операции");
                break;
            case 'print':
            case 'print2':
                if (!\Yii::$app->user->can('generation-print')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'download':
                if (!\Yii::$app->user->can('generation-download')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
            case 'index':
                if (!\Yii::$app->user->can('generation')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    public function actionComplete(string $id)
    {
        $message = '';
        
        try {
            $generation = Generation::getGenerationById($id);
            
            if ($generation->is_closed == true) {
                throw new \ErrorException('Отчет по данному заказу уже был сформирован ранее.');
            }
        } catch (\Exception $e) {
            \Yii::$app->response->statusCode = 400;
            
            return [
                'success' => false,
                'payload' => [],
                'error'   => $e->getMessage(),
            ];
        }
        
        $transaction = \Yii::$app->db->beginTransaction();
        $orderCompleteEvent = \Yii::createObject(OrderCompleteEvent::class);
        $orderCompleteEvent->setGenerations($generation);
        
        try {
            Event::trigger(OrderCompleteEvent::class, OrderCompleteEvent::COMPLETE_ORDER, $orderCompleteEvent);
            
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION,
                "Завершение заказа и формирование отчета для SAP", [["field" => "Модель заказа", "value" => $generation->attributes()]]);
            
            $generation->is_closed = true;
            $generation->save();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            $message = $e->getMessage();
        }
        
        $success = ($message === '') ? true : false;
        
        if ($success === false) {
            \Yii::$app->response->statusCode = 400;
        }
        
        return [
            'success' => $success,
            'payload' => [],
            'error'   => $message,
        ];
    }
    
    public function actionPackingCompleted()
    {
        $error = '';
        $data = null;
        $request = \Yii::$app->request->post();
        
        $erpConductor = \Yii::createObject(ErpOrdersConductor::class);
        
        try {
            if (!array_key_exists('order_id', $request) || !array_key_exists('packing_status', $request)) {
                throw new \ErrorException('Не был передан список всех необходимых параметров');
            }
            
            $data = $erpConductor->packingCompleted($request['order_id'], $request['packing_status']);
        } catch (\ErrorException $e) {
            \Yii::$app->response->statusCode = 422;
            $error = $e->getMessage();
        }
        
        return [
            'state'   => ($error == '') ? true : false,
            'payload' => ($data !== null) ? $data : '',
            'error'   => ($error === '') ? '' : $error,
        ];
    }
    
    public function actionRegistrationCompleted()
    {
        $error = '';
        $data = null;
        $request = \Yii::$app->request->post();
        
        $erpConductor = \Yii::createObject(ErpOrdersConductor::class);
        
        try {
            $data = $erpConductor->registrationCompleted($request['order_id'], $request['registration_status']);
        } catch (\ErrorException $e) {
            \Yii::$app->response->statusCode = 422;
            $error = $e->getMessage();
        }
        
        return [
            'state'   => ($error == '') ? true : false,
            'payload' => ($data !== null) ? $data : '',
            'error'   => ($error === '') ? '' : $error,
        ];
    }
    
}