<?php

namespace app\modules\itrack\components;

use app\modules\itrack\components\dto\SuzMdlpReportStatusDoc;
use app\modules\itrack\models\Constant;
use linslin\yii2\curl\Curl;
use yii\log\Logger;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\erp\ErpOrdersConductor;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\IsmDoc;
use app\modules\itrack\models\IsmLog;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\SuzConnectors;
use Exception;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;

/**
 * Система управления заказами - для криптохвостов
 * Class Suz
 *
 * @package app\modules\itrack\components
 * @todo    try-catch для логиррования ошибок
 */
class Suz
{
    const DEBUG_MODE = false;
    const API_BASE_URL = '';
    public const SERIAL_NUMBER_TYPE_SELF_MADE = 'SELF_MADE';

    public $connection;
    public $connectionId;
    public $connectionName;
    protected $connector;

    /**
     *
     * @param $connectionId
     *
     * @throws \yii\db\Exception
     */
    public function __construct($connectionId) {
        $this->connector = SuzConnectors::findOne($connectionId);

        /** проверяем, есть ли id коннекта по умолчанию и есть ли для него конфигурация */
        if (empty($this->connector)) {
            static::db_log(['has_error' => true, "data" => "Не найдены реквизиты для подключения $connectionId"]);
            throw new Exception('Configuration Error: No ISM default connection data');
        }

        $this->connectionId = $connectionId;
        $this->connectionName = $this->connector->name;
        $this->connection = $this->getParams();
    }

    /**
     * @param int $connectionId
     * @param Fns $fns
     *
     * @throws Exception
     */
    public static function setMdlpDocIdBySuzReportId($connectionId, Fns $fns)
    {
        $mdlpClient = new ISMarkirovka($connectionId);

        /*
         * если кривой, то пока пропускаем эту проверку
         */
        if (!$mdlpClient->isCorrectParams()) {
            $fns->state = Fns::STATE_RESPONCE_SUCCESS;
            $fns->save(false);

            return true;
        }

        $reportId = $fns->fns_state;
        // запрос информации из МДЛП по СУЗ reportId
        $mdlpDocuments = $mdlpClient->getInfoBySuzReportId($reportId);
        $items = $mdlpDocuments['items'] ?? [];

        if (!$items) {
            throw new \Exception('Не получен ответ из МДЛП по идентификатору отчета СУЗ, #' . $reportId);
        }

        //        $documents = self::groupDocuments($items);
        $documents = $items;

        $isAccepted = false;
        //        foreach ($documents['ids'] as $documentId) {
        // если документов по document_id в ответе несколько, - берем последний документ, ориентируемся по document_id
        $mdlpDocument = self::getLastDocument($documents);
        if (($status = $mdlpDocument['processing_document_status'] ?? null)) {
            $bodyDocument = json_encode($mdlpDocument);
            switch ($status) {
                case ISMarkirovka::SUZ_DOC_MDLP_STATUS_ACCEPTED:
                case ISMarkirovka::SUZ_DOC_MDLP_STATUS_PARTIAL:
                    /** Создаем документ */
                    IsmDoc::createFromArray(
                        [
                            'document_id'    => $mdlpDocument['document_id'],
                            'request_id'     => $mdlpDocument['request_id'],
                            'type'           => $mdlpDocument['doc_type'],
                            'status'         => IsmDoc::STATUS_SENDED,
                            'body'           => $bodyDocument,
                            'operation_id'   => $fns->id,
                            'connection_id'  => $connectionId,
                            'callback_token' => md5($fns->created_at . $fns->created_time . $fns->id)
                        ]
                    );
                    //в общий лог ИСМ
                    IsmLog::createFromArray(
                        [
                            'operation_id' => $fns->id,
                            'body'         => $bodyDocument,
                            'log_type'     => IsmLog::ISM_LOG_TYPE_CHECK_DOC_STATUS,
                        ]
                    );
                    $fns->state = Fns::STATE_SENDING;
                    $isAccepted = true;
                    break;
                case ISMarkirovka::SUZ_DOC_MDLP_STATUS_PROCESSING:
                    $isAccepted = false;
                    break;
                case ISMarkirovka::SUZ_DOC_MDLP_STATUS_REJECTED:
                    $fns->state = Fns::STATE_RESPONCE_ERROR;
                    IsmLog::createFromArray(
                        [
                            'operation_id' => $fns->id,
                            'body'         => $bodyDocument,
                            'log_type'     => IsmLog::ISM_LOG_TYPE_GET_ERR,
                        ]
                    );
                    throw new Exception('Документ не принят в МДЛП');
                    break;
                case ISMarkirovka::SUZ_DOC_MDLP_STATUS_TECH_ERROR:
                    $fns->state = Fns::STATE_RESPONCE_ERROR;
                    //в общий лог ИСМ
                    IsmLog::createFromArray(
                        [
                            'operation_id' => $fns->id,
                            'body'         => $bodyDocument,
                            'log_type'     => IsmLog::ISM_LOG_TYPE_GET_ERR,
                        ]
                    );
                    throw new Exception(
                        'В процессе обработки документа из СУЗ в МДЛП произошла ошибка: ' . $mdlpDocument['processing_document_status']
                    );
                    break;
            }
            $fns->save(false);
        }

        //}

        return $isAccepted;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private static function getLastDocument(array $data)
    {
        usort(
            $data,
            function ($a, $b) {
                if ($a['processing_document_status'] == ISMarkirovka::SUZ_DOC_MDLP_STATUS_ACCEPTED) {
                    return 1;
                }
                if ($b['processing_document_status'] == ISMarkirovka::SUZ_DOC_MDLP_STATUS_ACCEPTED) {
                    return -1;
                }
                if ($a['date'] == $b['date']) {
                    return 0;
                }

                return ($a['date'] < $b['date']) ? -1 : 1;
            }
        );

        return array_pop($data);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private static function groupDocuments(array $items)
    {
        $documents = $ids = [];
        foreach ($items as $mdlpDocument) {
            $documentId = $mdlpDocument['document_id'] ?? null;
            if ($documentId) {
                $ids[] = $documentId;
                $documents[$documentId][] = $mdlpDocument;
            }
        }

        return [
            'ids'       => $ids,
            'documents' => $documents,
        ];
    }

    /**
     * Проверка отправленных через  SUZ в Fns доков
     */
    public static function checkFnsDoc()
    {
        $interval = Constant::get('intervalAfterUpdateStateOnSuzReport');
        if (empty($interval)) {
            $interval = '30 min';
        }
        $docs = Fns::find()
            ->andWhere(['state' => Fns::STATE_SENDING_SUZ])
            ->andWhere(new Expression('sended_at + interval\'' . $interval . '\' < now()'))
            ->all();

        foreach ($docs as $fns) {
            $trans = Yii::$app->db->beginTransaction();
            try {
                $connectionId = $fns->object->suz_uid ?? null;
                $connectionUsoId = $fns->object->uso_uid ?? null;
                $suz = new Suz($connectionId);
                $ret = $suz->getReportStatus($fns->fns_state, $fns->id);

                static::db_log(['typeof' => 1, 'src_uid' => $fns->id, "data" => 'Запрос статуса отчета ' . $fns->id]);

                if ($ret['reportStatus'] == 'SENT') {
                    try {
                        static::db_log(['typeof' => 1, 'src_uid' => $fns->id, 'data' => 'Отчет принят ' . $fns->id]);
                        $state = false;

                        if (empty($connectionUsoId)) {
                            //МДЛП не подкллючен. считаем что все ок...
                            $state = true;
                            $fns->state = Fns::STATE_RESPONCE_SUCCESS;
                            $fns->save(false);
                        } else {
                            if (self::setMdlpDocIdBySuzReportId($connectionUsoId, $fns)) {
                                $state = true;
                            }
                            sleep(30);
                        }

                        if ($state && Constant::get('valpharmErpEnabled') == 'true') {
                            $erpOrderConductor = Yii::createObject(ErpOrdersConductor::class);
                            $generations = Generation::find()
                                ->where(
                                    [
                                        'product_uid' => $fns->product_uid,
                                    ]
                                )
                                ->all();

                            foreach ($generations as $generation) {
                                $generation->registration_status = 'Принят СУЗ';
                                $generation->sent_to_suz = date(DATE_ISO8601, time());
                                $generation->save();

                                $erpOrderConductor->registrationCompleted($generation->id, 'Принят СУЗ');
                            }
                        }
                    } catch (\Exception $exception) {
                    }
                } elseif ($ret['reportStatus'] == 'REJECTED') {
                    //в общий лог ИСМ
                    IsmLog::createFromArray(
                        [
                            'operation_id' => $fns->id,
                            'body'         => json_encode($ret),
                            'log_type'     => IsmLog::ISM_LOG_TYPE_GET_ERR,
                        ]
                    );

                    $fns->state = Fns::STATE_RESPONCE_ERROR;
                    $fns->makeNotify();
                    $fns->save(false);

                    static::db_log(
                        [
                            'has_error' => true,
                            'typeof'    => 1,
                            'src_uid'   => $fns->id,
                            'data'      => "Отчет отклонен $fns->id (" . json_encode($ret) . ")",
                        ]
                    );
                }
                $fns->save(false);
                $trans->commit();
            } catch (Exception $ex) {
                $trans->rollBack();
                echo $ex->getMessage() . PHP_EOL;
            }
            
        }
    }

    /**
     * Отправка МДЛП дока через СУЗ
     *
     * @param Fns $fns
     *
     * @return boolean
     */
    static function sendFnsDoc(Fns $fns)
    {
        if ($fns->operation_uid == Fns::OPERATION_PACK_ID)
        {

            try {
                $params = $fns->getParams();
                $codes = [];
                $res = Yii::$app->db->createCommand(
                    "SELECT code,childrens FROM _get_codes_array(:codes) as codes",
                    [':codes' => $fns->codes]
                )->queryAll();

                foreach ($res as $r) {
                    $childs = pghelper::pgarr2arr($r['childrens']);

                    // код без крипты отправка отчета в СУЗ невозможна
                    if (empty($childs)) {
                        return false;
                    }

                    $codes[] = '01' . $fns->product->nomenclature->gtin . '21' . $r['code'] . chr(29) . str_replace(
                            '~',
                            chr(29),
                            array_pop($childs)
                        );
                }

                if ($fns->object->suz_report) {
                    $connectionId = $fns->object->suz_uid ?? null;
                    //отправка через СУЗ
                    $suz = new Suz($connectionId);

                    $p = [
                        'codes'           => $codes,
                        'expdate'         => str_replace(
                            " ",
                            ".",
                            $params["expiration_date"] ?? $fns->product->expdate_full
                        ),
                        'order_type'      => $params["order_type"] ?? $fns->product->nomenclature->fns_order_type,
                        'series'          => $params["series_number"] ?? $fns->product->series,
                        'subject_id'      => $params["subject_id"] ?? $fns->object->fns_subject_id,
                        'owner_id'        => $params["owner_id"] ?? $fns->product->nomenclature->fns_owner_id ?? null,
                        'fns_id'          => $fns->id,
                        'production_date' => preg_replace(
                            '#\:\d{2}$#si',
                            '',
                            (new \DateTime($fns->cdt))->format('d.m.Y H:i:sP')
                        ), // dd.mm.yyyy hh:mm:ss±hh+tt
                    ];

                    //в общий лог ИСМ
                    $ismLog = new IsmLog();
                    $ismLog->operation_id = $fns->id;
                    $ismLog->body = json_encode($p);
                    $ismLog->log_type = IsmLog::ISM_LOG_TYPE_SENDDOC;
                    $ismLog->save(false);

                    static::db_log(
                        [
                            'has_error' => false,
                            'typeof'    => 1,
                            'src_uid'   => $fns->id,
                            "data"      => "Отправка отчета 311 $fns->id",
                        ]
                    );
                    // отправка в МДЛП через СУЗ
                    $ret = $suz->sendReport311($p);

                    //в общий лог ИСМ
                    $ismLog = new IsmLog();
                    $ismLog->operation_id = $fns->id;
                    $ismLog->body = json_encode($ret);
                    $ismLog->log_type = IsmLog::ISM_LOG_TYPE_RESPONSE_DOC;
                    $ismLog->save(false);

                    static::db_log(
                        [
                            'has_error' => false,
                            'typeof'    => 1,
                            'src_uid'   => $fns->id,
                            "data"      => "Успешная отправка $fns->id: " . json_encode($ret),
                        ]
                    );

                    //статус у текущего дока
                    $fns->fns_state = $ret['reportId'];
                    $fns->state = Fns::STATE_SENDING_SUZ;
                    $fns->prev_uid = null;
                    $fns->sended_at = new \yii\db\Expression('now()');
                    $fns->save(false);
                }
            } catch (Exception $ex) {
                //фиксируем ошибку
                $ismLog = new IsmLog();
                $ismLog->operation_id = $fns->id;
                $ismLog->body = $ex->getMessage();
                $ismLog->log_type = IsmLog::ISM_LOG_TYPE_GET_ERR;
                $ismLog->save(false);

                $fns->state = Fns::STATE_SEND_ERROR;
                $fns->fns_log = $ex->getMessage();
                $fns->prev_uid = null;
                $fns->sended_at = new \yii\db\Expression('now()');
                $fns->makeNotify();
                $fns->save(false);

                static::db_log(
                    [
                        'has_error' => true,
                        'typeof'    => 1,
                        'src_uid'   => $fns->id,
                        'data'      => "Ошибка отправки $fns->id: " . $ex->getMessage(),
                    ]
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Проверка флга по отправке отчетов в СУЗ
     *
     * @param $connectionId
     *
     * @return bool
     */
    static function canReport($connectionId)
    {
        return true;
    }

    /**
     * @param $url
     * @param $client_token
     * @param $omsId
     *
     * @return array
     * @throws BadRequestHttpException
     */
    static function checkPing($url, $client_token, $omsId)
    {
        $curl = new Curl();
        $curl->setOption(CURLOPT_SSL_VERIFYPEER, false)
            ->setOption(CURLOPT_HTTPHEADER, ['clientToken: ' . $client_token])
            ->get($url . '/ping?omsId=' . $omsId);

        if ($curl->responseCode !== 200) {
            throw new BadRequestHttpException('Сервис (СУЗ ' . $url . ') недоступен. Ответ:' . $curl->response);
        }

        return ['status' => 200, 'message' => 'ping Ok'];
    }

    /**
     * @param $params
     *
     * @throws \yii\db\Exception
     */
    static function db_log($params)
    {
        Yii::$app->db->createCommand(
            "INSERT INTO suz_log (typeof,src_uid,has_error,data) VALUES (:typeof,:src_uid,:has_error,:data)",
            [
                ":typeof"    => $params["typeof"] ?? 0,
                ":src_uid"   => empty($params["src_uid"]) ? null : $params["src_uid"],
                ":has_error" => $params["has_error"] ?? false,
                ":data"      => $params["data"] ?? "нет данных",
            ]
        )->execute();
    }

    /**
     * Логирование
     *
     * @param type $message
     * @param int $level
     */
    private static function log($message, $level = Logger::LEVEL_INFO)
    {
        Yii::getLogger()->log($message, $level, 'suz');
        echo $message . PHP_EOL;
    }

    /**
     * настройки подключения к СУЗ
     *
     * @return array
     */
    public function getParams()
    {
        return [
            'server_host'  => preg_replace('#\/$#si', '', $this->connector->url),
            'server_port'  => 0,
            'client_token' => $this->connector->client_token,
            'omsId'        => $this->connector->omsid,
            'template'     => $this->connector->templateid,
            'is_905'       => true,
            'freeCode'     => (bool)$this->connector->freecode,
            'paymentType'  => $this->connector->paymenttype,
        ];
    }

    /**
     * Отправка отчета об использовании кодов (мдлп 311)
     * 
     * @param type $params
     * $params["fns_id"] - идентификатор отчета - для логов
     * $params["codes"] - Массив кодов sntins
     * $params["expdate"] - срок годности по доке или дд.мм.уууу или уууу-мм-дд
     * $params["order_type"] - 1/2 тип заказа
     * $params["series"] - Серия товарки
     * $params["subject_id"] - фнсид отправителя
     * $params["owner_id"] = длинный ид собственника - при контрактном произвосдтве ордер_тип = 2
     * $params["production_date"] = Дата   производства (dd.mm.yyyyhh:mm:ss±hh).   Не   должна опережать  дату  создания  заказа  на эмиссию КМ.
     *
     * @return type
     * @throws Exception
     */
    public function sendReport311($params)
    {
        try {
            $this->ping();

            $data = [
                'sntins'         => $params["codes"],
                'usageType'      => 'VERIFIED',
                'expirationDate' => $params["expdate"],
                'orderType'      => (int)$params['order_type'],
                'seriesNumber'   => $params['series'],
                'subjectId'      => $params['subject_id'],  //ид мдлп - цифры - короткий
                'productionDate' => $params['production_date'],
            ];

            if ($params['order_type'] == 2) {
                $data['ownerId'] = $params["owner_id"]; //36 знаков - длинный
            }

            $curl = new Curl();
            $response = $curl
                ->setOption(CURLOPT_POSTFIELDS, json_encode($data))
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen(json_encode($data)),
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->post($this->getUrl() . '/utilisation?omsId=' . $this->connection["omsId"]);

            $data['sntins'] = "[skipped]";

            static::db_log(
                [
                    'has_error' => false,
                    'typeof'    => 1,
                    'src_uid'   => $params["fns_id"],
                    "data"      => "Отправка 311 в суз" . json_encode($data),
                ]
            );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Отправка отчета 311.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    ) . print_r($data, true) . "\nОтвет: " . print_r($response, true)
                );
            }

            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка отправки отчета 311'
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка отправки отчета 311: ' . $ex->getMessage());
            }

            $this->log("SendReport311: {$params["fns_id"]} - unsuccess");

            static::db_log(
                [
                    'has_error' => true,
                    'typeof'    => 1,
                    'src_uid'   => $params["fns_id"],
                    "data"      => "Ошибка отправки отчета 311 в суз: " . $ex->getMessage(),
                ]
            );

            throw new Exception('Ошибка отправки отчета 311: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log("SendReport311: {$params["fns_id"]} - success - " . $response["reportId"]);

        static::db_log(
            [
                'has_error' => false,
                'typeof'    => 1,
                'src_uid'   => $params["fns_id"],
                "data"      => "Успех отправки отчета 311 в суз",
            ]
        );

        return $response;
    }

    /**
     * Запрос справочника товарных карт зарегистрированных в СУЗ
     * 
     * @return type массив gtin & name
     * @throws Exception
     */
    public function getProducts()
    {
        try {
            $this->ping();
            $curl = new Curl();

            echo "@" . PHP_EOL;
            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->get(
                    $this->getUrl() . '/product/info?' . http_build_query(
                        [
                            'omsId' => $this->connection["omsId"],
                        ]
                    )
                );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Справочник продуктов.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    )
                );
            }
            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка получения справочника товарных карт: ' . $orderId
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка получения справочника товарных карт: ' . $ex->getMessage());
            }

            $this->log("getProducts: - unsuccess - " . $ex->getMessage());
            throw new Exception('Ошибка получения справочника товарных карт: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log("getProducts: - success");

        return $response["products"];
    }

    /**
     * 3.1.8	Получить статус бизнес-заказов (Get status orders)
     * 
     * @return type
     * @throws Exception
     */
    public function getOrders() {
        try {
            $this->ping();
            $curl = new Curl();

            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->get(
                    $this->getUrl() . '/orders?' . http_build_query(
                        [
                            'omsId' => $this->connection["omsId"],
                        ]
                    )
                );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Список заказов.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    )
                );
            }

            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка получения статуса бизнес-заказов: ' . $orderId
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка получения статуса бизнес-заказов: ' . $ex->getMessage());
            }

            $this->log("getOrders: - unsuccess - " . $ex->getMessage());
            throw new Exception('Ошибка получения статуса бизнес-заказов: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log("getOrders: - success");

        return $response;
    }

    /**
     * 2.1.10	Получить статус обработки отчёта (Get status processing report)
     * 
     * @return type
     * @throws Exception
     */
    public function getReportStatus($reportId, $fnsid = null) {
        try {
            $curl = new Curl();

            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->get(
                    $this->getUrl() . '/report/info?' . http_build_query(
                        [
                            'omsId'    => $this->connection["omsId"],
                            'reportId' => $reportId,
                        ]
                    )
                );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Статус отчета.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    )
                );
            }

            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка получения статуса отчета: ' . $orderId
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка получения статуса отчета: ' . $ex->getMessage());
            }

            $this->log("getReport311Status: " . $fnsid . " - unsuccess - " . $ex->getMessage());
            throw new Exception('Ошибка получения статуса отчета: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log("getReport311Status: " . $fnsid . " - success - " . $response["reportStatus"]);

        return $response;
    }

    /**
     * Получение кодов от СУЗ
     *
     * @param string $orderId - ид заказа
     * @param string $gtin - GTIN
     * @param string $count - количество кодов в заказе
     * @param array $params
     *
     * @return type - массив кодов
     * @throws \Exception
     */
    public function getOrder(string $orderId, string $gtin, string $count, array $params = []) 
    {
        $codes = [];
        $blockId = "0";
        try {
            $this->ping();
            $curl = new Curl();
            $gen = preg_replace("#^\d+\/#si", "", $params["generation"] ?? '');
            static::db_log(
                [
                    'has_error' => false,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Запрос кодов по заказу " . $params["generation"] . " (" . $params["generation_uid"] . ")",
                ]
            );

            do {
                echo "@" . PHP_EOL;
                $response = $curl
                    ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                    ->setOption(
                        CURLOPT_HTTPHEADER,
                        [
                            'clientToken: ' . $this->connection["client_token"],
                        ]
                    )
                    ->setOption(CURLOPT_TIMEOUT, 120)
                    ->get(
                        $this->getUrl() . '/codes?' . http_build_query(
                            [
                                'omsId'       => $this->connection["omsId"],
                                'orderId'     => $orderId,
                                'gtin'        => $gtin,
                                'quantity'    => $count,
                                'lastBlockId' => $blockId,
                            ]
                        )
                    );

                if (self::DEBUG_MODE) {
                    $this->log(
                        "Получение кодов.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                            $curl->getRequestHeaders(),
                            true
                        )
                    );
                }
                if (!empty($response)) {
                    $response = json_decode($response, true);
                }

                if ($curl->responseCode !== 200) {
                    throw new Exception(
                        is_array($response) ? implode(
                            ", ",
                            $response["globalErrors"] ?? [
                                print_r($response["fieldErrors"] ?? $response, true),
                            ] ?? []
                        ) : 'Ошибка получения кодов для заказа: ' . $orderId
                    );
                }
                $blockId = $response["blockId"];
                $codes = array_merge($codes, $response["codes"]);
            } while (count($codes) < $count);
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка получения кодов для заказа: ' . $ex->getMessage());
            }

            $this->log(
                "getOrder: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - unsuccess - " . $ex->getMessage(
                )
            );
            static::db_log(
                [
                    'has_error' => true,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Запрос кодов по заказу - ОШИБКА - " . $params["generation"] . " (" . $params["generation_uid"] . ")",
                ]
            );
            throw new Exception('Ошибка получения кодов для заказа: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        static::db_log(
            [
                'has_error' => false,
                'typeof'    => 0,
                'src_uid'   => $gen,
                "data"      => "Запрос кодов по заказу - УСПЕХ - " . $params["generation"] . " (" . $params["generation_uid"] . ") колво: " . count(
                        $codes
                    ),
            ]
        );
        $this->log(
            "getOrder: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - success - " . count(
                $codes
            )
        );

        return $codes;
    }

    /**
     * 3.1.5	Закрыть подзаказ по заданному GTIN (Close IC array for the specified product GTIN)
     * 
     * @param string $orderId
     * @param string $gtin
     * @param string $blockId
     *
     * @return type
     * @throws Exception
     */
    public function closeOrder(string $orderId, string $gtin, string $blockId = "0", array $params = []) {
        try {
            $this->ping();
            $curl = new Curl();
            $gen = preg_replace("#^\d+\/#si", "", $params["generation"] ?? '');
            static::db_log(
                [
                    'has_error' => false,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Закрытие заказа " . $params["generation"] . " (" . $params["generation_uid"] . ")",
                ]
            );


            echo "@" . PHP_EOL;
            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->post(
                    $this->getUrl() . '/buffer/close?' . http_build_query(
                        [
                            'omsId'       => $this->connection["omsId"],
                            'orderId'     => $orderId,
                            'gtin'        => $gtin,
                            'lastBlockId' => $blockId,
                        ]
                    )
                );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Закрытие заказа.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    )
                );
            }
            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка закрытия заказа: ' . $orderId
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка закрытия заказа: ' . $ex->getMessage());
            }

            $this->log(
                "closeOrder: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - unsuccess - " . $ex->getMessage(
                )
            );
            static::db_log(
                [
                    'has_error' => true,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Закрытие заказа - ОШИБКА -" . $params["generation"] . " (" . $params["generation_uid"] . ") " . $ex->getMessage(
                        ),
                ]
            );
            throw new Exception('Ошибка закрытия заказа: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log(
            "closeOrder: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - success"
        );

        return $response;
    }

    /**
     * Статус генерации КМ
     * вход:
     *
     * @param string $orderId
     * @param string $gtin
     *
     * @return type - массив
     * array(9) {
     *   ["leftInBuffer"]=>int(0)
     *   ["poolsExhausted"]=>bool(false)
     *   ["totalCodes"]=>int(1)
     *   ["unavailableCodes"]=>int(0)
     *   ["availableCodes"]=>int(1)
     *   ["orderId"]=>string(36) "7ea5a1b4-3a70-4893-8a7a-7012e7af8c64"
     *   ["gtin"]=>string(14) "01334567894339"
     *   ["bufferStatus"]=>string(7) "PENDING"
     *   ["omsId"]=>string(6) "123456"
     * }
     * @throws Exception
     */
    public function orderStatus(string $orderId = "", string $gtin, array $params = [])
    {
        try {
            $this->ping();

            $gen = preg_replace("#^\d+\/#si", "", $params["generation"] ?? '');
            static::db_log(
                [
                    'has_error' => false,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Статус заказа " . $params["generation"] . " (" . $params["generation_uid"] . ")",
                ]
            );

            $curl = new Curl();
            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->get(
                    $this->getUrl() . '/buffer/status?' . http_build_query(
                        [
                            'omsId'   => $this->connection["omsId"],
                            'orderId' => $orderId,
                            'gtin'    => $gtin,
                        ]
                    )
                );

            if (self::DEBUG_MODE) {
                $this->log(
                    "Статус заказа.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    )
                );
            }

            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка запроса статуса для заказа: ' . $orderId
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка запроса статуса заказа: ' . $ex->getMessage());
            }
            $this->log(
                "orderStatus: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - unsuccess - " . $ex->getMessage(
                )
            );
            static::db_log(
                [
                    'has_error' => true,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Статус заказа - ОШИБКА - " . $params["generation"] . " (" . $params["generation_uid"] . ") " . $ex->getMessage(
                        ),
                ]
            );
            throw new Exception('Ошибка запроса статуса заказа: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        static::db_log(
            [
                'has_error' => false,
                'typeof'    => 0,
                'src_uid'   => $gen,
                "data"      => "Статус заказа -УСПЕХ-" . $params["generation"] . " (" . $params["generation_uid"] . ") - " . $response["bufferStatus"] . ' - ' . $response["leftInBuffer"],
            ]
        );
        $this->log(
            "orderStatus: " . ($params["generation"] ?? '') . " - " . ($params["generation_uid"] ?? '') . " - success - " . $response["bufferStatus"] . ' - ' . $response["leftInBuffer"]
        );

        return $response;
    }

    /**
     * Создание заказа на СУЗ v.9.05
     * вход: params['gtin'] - gtin заказа
     *       params['codes'] - массив строк с кодами
     *       params['ownerId'] - идентфиикатор собственника
     *       params['subjectId'] - идентфиикатор производителя
     * 
     * выход:
     * array(3) {
     *           ["omsId"]=>  string(6) "pharma"
     *           ["orderId"]=> string(36) "61f7c69f-15b5-482a-a252-d5abc0fa87b5"
     *           ["expectedCompleteTimestamp"]=>  int(12615)
     *          }
     *
     * @param array $params
     *
     * @return mixed
     * @throws Exception
     */
    public function createOrder(array $params = [])
    {
        $gen = preg_replace('#^\d+\/#si', '', $params['params']['generation'] ?? '');

        try {
            $this->ping();

            static::db_log(
                [
                    'has_error' => false,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    'data'      => 'Создание заказа ' . $params['params']['generation'] . ' (' . $params['params']['generation_uid'] . ')',
                ]
            );

            $nomenclature = $this->checkNomenclature($params);

            $data = [
                'products'  => [
                    [
                        'gtin'             => $params['gtin'],
                        'quantity'         => count($params['codes'] ?? []),
                        'serialNumberType' => self::SERIAL_NUMBER_TYPE_SELF_MADE,
                        'serialNumbers'    => $params['codes'] ?? [],
                        'templateId'       => $this->connection['template'] ?? 2,
                    ],
                ],
                'subjectId' => $params['subjectGuid'],
                'freeCode'  => (bool)!$nomenclature->is_payment,
            ];

            if ($this->connection['freeCode']) {
                $data['paymentType'] = $this->connection['paymentType'];
            }

            $curl = new Curl();
            $response = $curl
                ->setOption(CURLOPT_POSTFIELDS, json_encode($data))
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(
                    CURLOPT_HTTPHEADER,
                    [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen(json_encode($data)),
                        'clientToken: ' . $this->connection["client_token"],
                    ]
                )
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->post($this->getUrl() . '/orders?omsId=' . $this->connection["omsId"]);

            if (self::DEBUG_MODE) {
                $this->log(
                    "Создание заказа.\nЗапрос: " . $curl->getUrl() . "\nЗаголовки: " . print_r(
                        $curl->getRequestHeaders(),
                        true
                    ) . print_r($data, true)
                );
            }

            if (!empty($response)) {
                $response = json_decode($response, true);
            }

            if ($curl->responseCode !== 200) {
                throw new Exception(
                    is_array($response) ? implode(
                        ", ",
                        $response["globalErrors"] ?? [
                            print_r($response["fieldErrors"] ?? $response, true),
                        ] ?? []
                    ) : 'Ошибка создания заказа'
                );
            }
        } catch (Exception $ex) {
            if (self::DEBUG_MODE) {
                $this->log('Ошибка создания заказа: ' . $ex->getMessage());
            }

            $this->log(
                "createOrder: " . ($params["params"]["generation"] ?? '') . " - " . ($params["params"]["generation_uid"] ?? '') . " - unsuccess - " . $ex->getMessage(
                )
            );

            static::db_log(
                [
                    'has_error' => true,
                    'typeof'    => 0,
                    'src_uid'   => $gen,
                    "data"      => "Создание заказа -ОШИБКА- " . $params["params"]["generation"] . " (" . $params["params"]["generation_uid"] . ") " . $ex->getMessage(
                        ),
                ]
            );

            throw new Exception('Ошибка создания заказа: ' . $ex->getMessage());
        }

        if (self::DEBUG_MODE) {
            $this->log(print_r($response, true));
        }

        $this->log(
            "createOrder: " . ($params["params"]["generation"] ?? '') . " - " . ($params["params"]["generation_uid"] ?? '') . " - success - " . $response["orderId"]
        );

        static::db_log(
            [
                'has_error' => false,
                'typeof'    => 0,
                'src_uid'   => $gen,
                "data"      => "Создание заказа -УСПЕХ- " . $params["params"]["generation"] . " (" . $params["params"]["generation_uid"] . ") " . $response["orderId"],
            ]
        );

        return $response;
    }

    /**
     * @param array $params
     *
     * @return Nomenclature
     * @throws Exception
     */
    private function checkNomenclature($params): Nomenclature
    {
        $gtin = $params['gtin'] ?? null;
        $generationUid = $params['params']['generation_uid'] ?? null;

        $nomenclature = Nomenclature::findNomenclatureByGtin($gtin);

        if (!$nomenclature) {
            if ($generationUid && $generation = Generation::findOne($generationUid)) {
                $generation->applyStatusError();
            }
            throw new Exception(Yii::t('app', 'Не найдена номенклатура по gtin: {gtin}', ['gtin' => $gtin]));
        }

        return $nomenclature;
    }

    /**
     * Проверка доступности сервиса
     *
     * @throws Exception
     */
    public function ping()
    {
        $trys = Yii::$app->params["suz"]["ping_trys"] ?? 1;
        $curl = new Curl();

        for ($i = 1; $i <= $trys; $i++) {
            $response = $curl
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(CURLOPT_HTTPHEADER, ['clientToken: ' . $this->connection["client_token"]])
                ->get($this->getUrl() . '/ping?omsId=' . $this->connection["omsId"]);

            if ($curl->responseCode !== 200) {
                if($i < $trys)
                {
                    sleep(1);
                    continue;
                } else {
                    if (self::DEBUG_MODE) {
                        $this->log(
                            'Сервер недоступен: ' . $this->connection["server_host"] . '/ping?omsId=' . $this->connection["omsId"]
                        );
                    }
                    throw new Exception(
                        'Сервис (СУЗ ' . $this->connection["server_host"] . ') недоступен (ответ: ' . $response . ')'
                    );
                }
            }

            if (self::DEBUG_MODE) {
                $this->log('ping Ok');
            }

            return;
        }
    }

    /**
     * Урл сервиса
     * @return type
     */
    public function getUrl()
    {
        return $this->connection["server_host"];//. ':'. $this->connection["server_port"] . '/' . self::API_BASE_URL;
    }
}
