<?php

namespace app\modules\itrack\components;

use app\modules\itrack\models\Constant;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\FnsDoc;
use app\modules\itrack\models\IsmDoc;
use app\modules\itrack\models\IsmLog;
use app\modules\itrack\models\IsmToken;
use app\modules\itrack\models\Notify;
use app\modules\itrack\models\UsoCache;
use app\modules\itrack\models\UsoConnectors;
use Exception;

/**
 * Class ISMarkirovka
 *
 * @package app\modules\itrack\components
 * @todo    try-catch для логиррования ошибок
 */
class ISMarkirovka
{
    const DEBUG_MODE = false;
    const API_BASE_URL = '';
    const AUTH_TYPE_PASSWORD = 'PASSWORD';
    const AUTH_TYPE_SIGNED_CODE = 'SIGNED_CODE';

    const SUZ_DOC_MDLP_STATUS_ACCEPTED = 'ACCEPTED';
    const SUZ_DOC_MDLP_STATUS_PARTIAL = 'PARTIAL';
    const SUZ_DOC_MDLP_STATUS_PROCESSING = 'PROCESSING';
    const SUZ_DOC_MDLP_STATUS_REJECTED = 'REJECTED';
    const SUZ_DOC_MDLP_STATUS_TECH_ERROR = 'TECH_ERROR';

    protected $token = null;
    protected $tokenValidTill = null;
    protected $connectionName;
    protected $connectionId;
    protected $connector;

    /**
     * ISMarkirovka constructor.
     *
     * @param bool $connectionId
     *
     * @throws \Exception
     */
    public function __construct($connectionId = false)
    {
        // todo: убрать после теста $this->createConnector(Fns $document)
        $this->connector = UsoConnectors::findOne($connectionId);
        /** проверяем, есть ли id коннекта по умолчанию и есть ли для него конфигурация */
        if (empty($this->connector)) {
            throw new \Exception(sprintf('Configuration Error: ISM with ID %d not found.', $connectionId));
        }

        $this->connectionId = $connectionId;
        $this->connectionName = $this->connector->name;
    }

    /**
     * проверка статуса документа по идентификатору отправленного документа
     *
     * @param string $documentId
     * @param Fns $fns
     * @throws Exception
     */
    public function updateDocumentStatus(string $documentId, Fns $fns, IsmDoc $ismDoc)
    {
        $result = $this->getDocumentInfo($documentId);

        $localDocsIds = [
            $documentId => [
                'id'            => $ismDoc->id,
                'callbackToken' => $ismDoc->callback_token,
                'operationId'   => $ismDoc->operation_id,
                'callbackType'  => $ismDoc->callback_type,
            ]
        ];


        if (!empty($result) && in_array(
                $result['doc_status'] ?? '',
                [IsmDoc::ISM_STATUS_PROCESSED, IsmDoc::ISM_STATUS_FAILED_READY]
            )) {
            $itemToUpdate = [];
            $this->handleOutcomeDocStatus($result['doc_status'], $localDocsIds, $result, $itemToUpdate);
            foreach ($itemToUpdate as $status => $ids) {
                if ($status == IsmDoc::ISM_STATUS_FAILED) {
                    IsmDoc::updateAll(
                        ['status' => $status],
                        new \yii\db\Expression(
                            "id in (" . implode(",", $ids) . ") and (created + interval '20 min') < now()"
                        )
                    );
                } else {
                    IsmDoc::updateAll(['status' => $status], ['id' => $ids]);
                }
            }
        }
    }

    /**
     * Отправка документа в МДЛП (размер свыше 2МБ)
     *
     * @param FnsDoc $doc
     *
     * @throws \Exception
     */
    public function sendBigDoc(FnsDoc $doc)
    {
        $this->authIfNeed();

        $requestUuid = $this->genUuid();
        $ismParams = $this->getISMParams();

        if ($ismParams['auth_type'] == self::AUTH_TYPE_PASSWORD) {
            $sign = '';
        } else {
            $sign = $doc->getSign($this->connectionId);
            if (empty($sign)) {
                throw new Exception("Формировние подписи документа", 100);
            }
        }

        $params = [
            'hash_sum'   => hash('sha256', $doc->body),
            'sign'       => base64_encode($sign),
            'request_id' => $requestUuid,
        ];
        if (empty($sign)) {
            unset($params['sign']);
        }

        /**
         * Создаем документ (до отправки)
         */
        $ismDoc = new IsmDoc();
        $ismDoc->request_id = $requestUuid;
        $ismDoc->body = $doc->body;
        $ismDoc->type = $doc->type;
        $ismDoc->operation_id = $doc->operationId;
        $ismDoc->callback_token = $doc->callbackToken;
        $ismDoc->callback_type = $doc->callbackType;
        $ismDoc->status = IsmDoc::STATUS_NEW;
        $ismDoc->connection_id = $this->connectionId;
        $ismDoc->save(false);

        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/documents/send_large';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        //var_dump($response);

        $i = 0;
        $body = $doc->body;
        $full_len = strlen($body);
        do {
            $data = substr($body, 0, 1000 * 1000);
            $body = substr($body, strlen($data));
            $url = $response['link'];
            $resp = $this->putCurl($url, $data, ($i) . "-" . ($i + strlen($data) - 1) . '/' . $full_len);
            var_dump($resp);
            $i += strlen($data);
        } while (strlen($body) > 0);

        $params['document_id'] = $response['document_id'];

        $url = $ismParams['api_basepath'] . '/documents/send_finished';
        $result = $this->sendCurl(
            $url,
            [
                'document_id' => $params['document_id'],
            ]
        );
        var_dump($result);
        $result = json_decode($result, true);

        $log = new IsmLog();
        $log->operation_id = $doc->operationId;
        $log->body = var_export($result, true);
        $log->log_type = IsmLog::ISM_LOG_TYPE_SENDDOC;
        $log->save(false);

        if (!is_array($response) || !isset($response['document_id']) || !is_array(
                $result
            ) || !isset($result['request_id'])) {
            throw new \Exception('Error while send document');
        }

        $ismDoc->document_id = $response['document_id'];
        $ismDoc->status = IsmDoc::STATUS_SENDED;
        $ismDoc->save(false);
    }

    /**
     * Отправка документа в ИС Маркировка
     *
     * @param FnsDoc $doc
     *
     * @throws \Exception
     * @todo: научить скрипт обрабатывать большие домументы (другой механизм загрузки >2Mb)
     */
    public function sendDoc(FnsDoc $doc)
    {
        $this->authIfNeed();

        $requestUuid = $this->genUuid();
        $ismParams = $this->getISMParams();

        if (strlen(base64_encode($doc->body)) >= 2048 * 1024) {
            $this->sendBigDoc($doc);

            return;
        }

        if ($ismParams['auth_type'] == self::AUTH_TYPE_PASSWORD) {
            $sign = '';
        } else {
            $sign = $doc->getSign($this->connectionId);
            if (empty($sign)) {
                throw new Exception("Формировние подписи документа", 100);
            }
        }

        $params = [
            'doc_type'   => $doc->type,
            'document'   => base64_encode($doc->body),
            'sign'       => base64_encode($sign),
            'request_id' => $requestUuid,
        ];

        if (empty($sign)) {
            unset($params['sign']);
        }

        /**
         * Создаем документ (до отправки)
         */
        $ismDoc = new IsmDoc();
        $ismDoc->request_id = $requestUuid;
        $ismDoc->body = $doc->body;
        $ismDoc->type = $doc->type;
        $ismDoc->operation_id = $doc->operationId;
        $ismDoc->callback_token = $doc->callbackToken;
        $ismDoc->callback_type = $doc->callbackType;
        $ismDoc->status = IsmDoc::STATUS_NEW;
        $ismDoc->connection_id = $this->connectionId;
        $ismDoc->save(false);

        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/documents/send';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);
        //var_dump($response);
        /**
         * Пишем историю отправки
         */
        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        $log = new IsmLog();
        $log->operation_id = $doc->operationId;
        $log->body = var_export($logResponse, true);
        $log->log_type = IsmLog::ISM_LOG_TYPE_SENDDOC;
        $log->save(false);

        if (!is_array($response) || !isset($response['document_id'])) {
            throw new \Exception('Error while send document');
        }

        $ismDoc->document_id = $response['document_id'];
        $ismDoc->status = IsmDoc::STATUS_SENDED;
        $ismDoc->save(false);
    }

    /**
     * Внутренний запрос 210 дока с ожиданием ответа
     *
     * @param type $code
     * @param type $codetype
     *
     * @return type
     */
    private function request210($code, $codetype)
    {
        $ismParams = $this->getISMParams();

        $fns = new Fns;
        $fns->load(
            [
                'operation_uid' => Fns::OPERATION_210,
                'fnsid'         => '210',
                'code'          => $code,
                'internal'      => true,
                'object_uid'    => \Yii::$app->user->getIdentity()->object_uid,
            ],
            ''
        );

        //генерация ид запроса
        $requestUuid = $this->genUuid();
        $doc = new FnsDoc(
            [
                'body'          => $fns->xml(['codetype_uid' => $codetype]),
                'type'          => '210',
                'operationId'   => '',
                'callbackToken' => '',
            ]
        );

        if ($ismParams['auth_type'] == self::AUTH_TYPE_PASSWORD) {
            $sign = '';
        } else {
            $sign = $doc->getSign($this->connectionId);
            if (empty($sign)) {
                throw new Exception("Формировние подписи документа", 100);
            }
        }

        $params = [
            'doc_type'   => $doc->type,
            'document'   => base64_encode($doc->body),
            'sign'       => base64_encode($sign),
            'request_id' => $requestUuid,
        ];

        if (empty($sign)) {
            unset($params['sign']);
        }

        //отправляем док
        $url = $ismParams['api_basepath'] . '/documents/send';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (!isset($response['document_id'])) {
            return null;
        }
        $docId = $response['document_id'];

        $t = time();
        $resp = null;
        while (($t + 60) > time() && $resp == null) {
            sleep(10);
            $url = $ismParams['api_basepath'] . '/documents/' . $docId . '/ticket';
            $response = $this->sendCurl($url, [], 'GET');
            $response = json_decode($response, true);
            if (isset($response['link'])) {
                $docLink = $response['link'];
                $ticketBody = $this->sendCurl($docLink, [], 'GET');
                if (!empty($ticketBody)) {
                    $resp = $ticketBody;
                }
            }
        }

        return $resp;
    }

    public function sgtin($offset = 0, $limit = 100)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'filter'     => [],
            'start_from' => $offset,
            'count'      => $limit,
        ];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/sgtin/filter';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.1.2. Метод для поиска информации о местах осуществления деятельности
     *
     * @param string $branchId
     *
     * @return type
     */
    public function getBranchInfo(string $branchId)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();

        $params = [
            'filter'     => [
                'branch_id' => $branchId,
            ],
            'start_from' => 0,
            'count'      => 1000,
        ];

        $url = $ismParams['api_basepath'] . '/reestr/branches/filter';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.5.3. Метод для получения публичной информации из реестра производимых ЛП
     *
     * @return array
     */
    public function getAnyPublicLPInfo()
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();

        $params = [
            'filter'     => [
            ],
            'start_from' => 0,
            'count'      => 1000,
        ];

        $url = $ismParams['api_basepath'] . '/reestr/med_products/public/filter';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.5.1. Метод для получения информации из реестра производимых организацией ЛП
     *
     * @return array
     */
    public function getPublicLPInfo()
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();

        $params = [
            'filter'     => [
            ],
            'start_from' => 0,
            'count'      => 1000,
        ];

        $url = $ismParams['api_basepath'] . '/reestr/med_products/current';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.5.2. Метод для получения детальной информации об производимом орагнизацией ЛП
     *
     * @param string $gtin
     *
     * @return array $response
     */
    public function getPublicGtinInfo($gtin)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();

        $url = $ismParams['api_basepath'] . '/reestr/med_products/' . $gtin;
        $responseRaw = $this->sendCurl($url, [], 'GET');
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.3.3. Метод поиска по общедоступному реестру КИЗ по списку значений
     *
     * @param type $code
     *
     * @return type
     */
    public function getPublicCodeInfo($code)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'filter' => [
                'sgtins' => [$code],
            ],
        ];

        $url = $ismParams['api_basepath'] . '/reestr/sgtin/public/sgtins-by-list';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * 8.3.4 Получение детальной информации о КИЗ и его ЛП
     *
     * @param type $code
     * @return type
     */
    public function getDetalSgtinInfo($code)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();

        $url = $ismParams['api_basepath'] . '/reestr/sgtin/' . $code;
        $responseRaw = $this->sendCurl($url, [], 'GET');
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Фильтрация по реестру виртуального склада
     *
     * @param type $storage
     * @param type $count
     * @param type $offset
     *
     * @return type
     */
    public function getStorageInfo($storage, $count = 50, $offset = 0)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'start_from' => $offset,
            'count'      => $count,
            'filter'     => [
                'storage_id' => $storage,
            ],
        ];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/virtual-storage/filter';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Получение данных из ИС Маркировка по коду
     *
     * @param type $code
     *
     * @return type
     */
    public function getCodeInfo($code)
    {
        ob_start();
        //если код содериться в нашей системе, надо ли отправлять запрос в маркировку?
        //        $code = \app\modules\itrack\models\Code::findOneByCode($code);
        //        if(!empty($code))
        //            return $code;
        //получение токена
        $this->authIfNeed();

        $codetype = strlen(
            $code
        ) == 18 ? \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP : \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL;

        $resp = $this->request210($code, $codetype);

        if ($codetype == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
            //надо запросить парента
            $respUp = $this->request210($code, 0);
        }

        if (\Yii::$app->response instanceof \yii\web\Response) {
            \Yii::$app->response->clearOutputBuffers();
            \Yii::$app->response->clear();
        }
        ob_clean();
        if (isset($respUp) && !empty($respUp)) {
            return [$resp, $respUp];
        }

        return [$resp];
    }

    /**
     * Запрос инфомрации по коду
     *
     * @param type $code
     *
     * @return type
     */
    public function codeInfo($code, $offset = 0, $limit = 100)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'start_from' => $offset,
            'count'      => $limit,
        ];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/sscc/' . $code . '/sgtins';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Запрос инфомрации по коду
     *
     * @param type $code
     *
     * @return type
     */
    public function codeHierarchy($code)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/sscc/' . $code . '/hierarchy';
        $responseRaw = $this->sendCurl($url, $params, 'GET');
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Запрос инфомрации по коду 8.4.3. полная иерархия третичной упаковки
     *
     * @param type $code
     *
     * @return type
     * ответ на /reestr/sscc/046700124600085111/full-hierarchy :
     * {"up":
     *          {"sscc":"046700124600085111","packing_date":"2020-04-27T09:17:06Z"},
     *  "down":{
     *          "sscc":"046700124600085111",
     *          "packing_date":"2020-04-27T09:17:06Z",
     *          "childs":[
     *                  {"sscc":"046065560000231712",
     *                      "packing_date":"2020-04-27T09:17:06Z",
     *                      "childs":[
     *                              {"sgtin":"0460655600277010C515C8C5ABM","sscc":"046065560000231712","internal_state":"in_circulation","gtin":"04606556002770","expiration_date":"2023-04-30T00:00:00Z","batch":"008TEST"},
     *                              {"sgtin":"0460655600277010C57C797A953","sscc":"046065560000231712","internal_state":"in_circulation","gtin":"04606556002770","expiration_date":"2023-04-30T00:00:00Z","batch":"008TEST"},
     *                              {"sgtin":"0460655600277010C5T1T0613E2","sscc":"046065560000231712","internal_state":"in_circulation","gtin":"04606556002770","expiration_date":"2023-04-30T00:00:00Z","batch":"008TEST"}
     *                      ]},
     *                  {"sscc":"046065560000231835",
     *                      "packing_date":"2020-04-27T09:17:06Z",
     *                      "childs":[
     *                              {"sgtin":"0460709845158310C60HB2M047X","sscc":"046065560000231835","internal_state":"in_circulation","gtin":"04607098451583","expiration_date":"2023-04-30T00:00:00Z","batch":"TEST04"},
     *                              {"sgtin":"0460709845158310C62EMH9607M","sscc":"046065560000231835","internal_state":"in_circulation","gtin":"04607098451583","expiration_date":"2023-04-30T00:00:00Z","batch":"TEST04"},
     *                              {"sgtin":"0460709845158310C63932K1PMB","sscc":"046065560000231835","internal_state":"in_circulation","gtin":"04607098451583","expiration_date":"2023-04-30T00:00:00Z","batch":"TEST04"}
     *                      ]
     *                  }
     *          ]
     *   }
     * }
     */
    public function codeFullHierarchy($code)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/sscc/' . $code . '/full-hierarchy';
        $responseRaw = $this->sendCurl($url, $params, 'GET');
        $response = json_decode($responseRaw, true);

        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Добалвение контаргента в список доверенных
     *
     * @throws \Exception
     * @todo: научить скрипт обрабатывать большие домументы (другой механизм загрузки >2Mb)
     */
    public function addPartner($inn)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'trusted_partners' => [
                $inn,
            ],
        ];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr/trusted_partners/add';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        /**
         * Пишем историю отправки
         */
        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Запрос партнеров в ИС Маркировка
     *
     * @throws \Exception
     * @todo: научить скрипт обрабатывать большие домументы (другой механизм загрузки >2Mb)
     */
    public function getPartners(array $filter)
    {
        $this->authIfNeed();
        $ismParams = $this->getISMParams();
        $params = [
            'filter'     => $filter,
            'start_from' => 0,
            'count'      => 15000,
        ];
        /**
         * Отправляем
         */
        $url = $ismParams['api_basepath'] . '/reestr_partners/filter';
        $responseRaw = $this->sendCurl($url, $params);
        $response = json_decode($responseRaw, true);

        /**
         * Пишем историю отправки
         */
        if (is_array($response)) {
            $logResponse = $response;
        } else {
            $logResponse = $responseRaw;
        }

        return $logResponse;
    }

    /**
     * Проверка статусов выгруженых документов
     *
     * @param string $checkStatus
     *
     * @return bool
     * @throws \Exception
     */
    public function updateDocStatus($checkStatus = IsmDoc::ISM_STATUS_PROCESSED): bool
    {
        if (!in_array(
            $checkStatus,
            [IsmDoc::ISM_STATUS_PROCESSED, IsmDoc::ISM_STATUS_FAILED, IsmDoc::ISM_STATUS_FAILED_READY]
        )) {
            throw new \Exception('Unknown update status');
        }

        /** ищем отправленные, но не обработанные документы */
        if (!$localDocs = IsmDoc::getSendedDocuments($this->connectionId)) {
            return false;
        }

        $dateTime = null;
        $localDocsIds = $this->formedDocIds($localDocs, $dateTime);
        unset($localDocs);

        $this->authIfNeed();

        if (empty($dateTime) || $dateTime < date('Y-m-d 00:00:00', time() - 3600 * 24 * 15)) {
            $dateTime = date('Y-m-d 00:00:00', time() - 3600 * 24 * 15);
        }

        $documents = $this->getOutcomeDocs(
            [
                'filter'     => [
                    'doc_status' => $checkStatus,
                    'start_date' => $dateTime,
                    'end_date'   => date('Y-m-d H:i:s'),
                ],
                'start_from' => 0,
                'count'      => 15000,
            ]
        );

        $itemsToUpdate = [];
        foreach ($documents['documents'] as $doc) {
            $this->handleOutcomeDocStatus($checkStatus, $localDocsIds, $doc, $itemsToUpdate);
        }

        foreach ($itemsToUpdate as $status => $ids) {
            if ($status == IsmDoc::ISM_STATUS_FAILED) {
                IsmDoc::updateAll(
                    ['status' => $status],
                    new \yii\db\Expression(
                        "id in (" . implode(",", $ids) . ") and (created + interval '20 min') < now()"
                    )
                );
            } else {
                IsmDoc::updateAll(['status' => $status], ['id' => $ids]);
            }
        }

        return true;
    }

    /**
     * @param string $checkStatus
     * @param array $localDocsIds
     * @param array $doc
     * @param array $itemsToUpdate
     *
     * @return bool
     * @throws Exception
     */
    private function handleOutcomeDocStatus(string $checkStatus, array $localDocsIds, array $doc, array &$itemsToUpdate)
    {
        $ismDocId = $doc['document_id'] ?? null;

        if ($ismDocId && isset($localDocsIds[$ismDocId])) {
            IsmLog::createFromArray(
                [
                    'operation_id' => $localDocsIds[$ismDocId]['operationId'],
                    'body'         => var_export($doc, true),
                    'log_type'     => IsmLog::ISM_LOG_TYPE_CHECK_DOC_STATUS,
                ]
            );

            $ticketBody = false;
            $ticketOpId = null;
            // если статус ISM_STATUS_PROCESSED или ISM_STATUS_FAILED_READY - забираем квитанцию
            if (in_array($checkStatus, [IsmDoc::ISM_STATUS_PROCESSED, IsmDoc::ISM_STATUS_FAILED_READY])) {
                $response = $this->getDocumentsTicket($ismDocId, false);
                // не получили квитанцию - документ не обработан, не обновляем статус.. в следующий раз заберем
                if (!isset($response['link'])) {
                    return false;
                }
                $ticketBody = $this->getDataTicketByLink($response['link']);
                // вытаскиваем ID операции из квитанции
                $ticketOpId = FnsDoc::getTicketOperationId($ticketBody);
                // вытаскиваем status из квитанции
                $ticketStatus = FnsDoc::getTicketStatus($ticketBody, $localDocsIds[$ismDocId]['operationId']);
                // Обработка статуса тикета
                //                    $this->handleStatusTicketDoc($ticketStatus, $ticketBody, $ticketOpId);
                //                }
            } else {
                $ticketStatus = IsmDoc::ISM_STATUS_FAILED;
            }

            if (!isset($itemsToUpdate[$ticketStatus])) {
                $itemsToUpdate[$ticketStatus] = [];
            }

            $itemsToUpdate[$ticketStatus][] = $localDocsIds[$ismDocId]['id'];

            /**
             * не сохраням в доках статус - так как ждем тикет
             */
            if ($ticketStatus == IsmDoc::ISM_STATUS_FAILED) {
                return;
            }

            $answerParams = [
                'tok'       => $localDocsIds[$ismDocId]['callbackToken'],
                'state'     => $this->getCallbackStatus($ticketStatus, $localDocsIds[$ismDocId]['operationId']),
                'fns_state' => $ticketOpId,
                'fns_log'   => $ticketBody, // as xml - as is
            ];

            if ($localDocsIds[$ismDocId]['callbackType']) {
                $answerParams['type'] = $localDocsIds[$ismDocId]['callbackType'];
            }
            if ($answerParams['type'] == 'usoCache') {
                $fns = UsoCache::find()->andWhere(['id' => $localDocsIds[$ismDocId]['operationId']])->one();
            } else {
                $fns = Fns::find()->andWhere(['id' => $localDocsIds[$ismDocId]['operationId']])->one();
            }

            try {
                if ($fns) {
                    $fns->answer($answerParams);
                }
            } catch (\Exception $ex) {
                IsmLog::createFromArray(
                    [
                        'operation_id' => $localDocsIds[$ismDocId]['operationId'],
                        'body'         => json_encode(
                            ['error' => $ex->getMessage(), 'document_id' => $ismDocId],
                            JSON_UNESCAPED_UNICODE
                        ),
                        'log_type'     => IsmLog::ISM_LOG_TYPE_GET_ERR,
                    ]
                );
            }
        }
    }

    /**
     * @param string $link
     *
     * @return bool|string
     */
    private function getDataTicketByLink(string $link): string
    {
        return $this->sendCurl($link, [], 'GET');
    }

    /**
     * @param string $statusTicket
     * @param string $ticketBody
     * @param string $operationId
     *
     * @throws Exception
     */
    /*
        private function handleStatusTicketDoc(string &$statusTicket, string $ticketBody, string $operationId)
        {
            $bodyTicketToArray = XmlService::xmlTicketDocToArray($ticketBody);
            $convertStatus = $this->getCallbackStatus($statusTicket);

            switch ($convertStatus) {
                case IsmDoc::STATE_RESPONSE_SUCCESS:
                    $statusTicket = IsmDoc::STATE_RESPONSE_SUCCESS;
                    break;
                case IsmDoc::STATE_RESPONSE_PARTED:
                    $statusTicket = IsmDoc::STATE_RESPONSE_PARTED;
                    break;
                case IsmDoc::STATE_RESPONSE_ERROR:
                    if (
                        $bodyTicketToArray['operation_result'] == 'Rejected' &&
                        (strpos($bodyTicketToArray['operation_comment'], 'ошибка на этапе подготовки ответа') !== false)
                    ) {
                        // ... ничего не делаем
                    } else {
                        $statusTicket = IsmDoc::STATE_RESPONSE_ERROR;
                    }
                    break;
            }

            // Фиксируем в логе данные тикета
            IsmLog::createFromArray([
                'operation_id' => $operationId,
                'body'         => json_encode($bodyTicketToArray),
                'log_type'     => IsmLog::ISM_LOG_TYPE_RESPONSE_DOC,
            ]);
        }
    */
    /**
     * @param string $status
     *
     * @return int
     * @throws Exception
     */
    private function getCallbackStatus($status, $operationId): int
    {
        $callbackStatus = null;
        $logType = null;

        switch ($status) {
            case IsmDoc::ISM_STATUS_PROCESSED:
                $callbackStatus = IsmDoc::STATE_RESPONSE_SUCCESS;
                $logType = IsmLog::ISM_LOG_TYPE_GET_OK;
                break;
            case IsmDoc::ISM_STATUS_FAILED:
            case IsmDoc::ISM_STATUS_FAILED_READY:
                $callbackStatus = IsmDoc::STATE_RESPONSE_ERROR;
                $logType = IsmLog::ISM_LOG_TYPE_GET_ERR;
                break;
            case IsmDoc::ISM_STATUS_PARTED:
                $callbackStatus = IsmDoc::STATE_RESPONSE_PARTED;
                $logType = IsmLog::ISM_LOG_TYPE_GET_PARTED;
                break;
            default:
                throw new \Exception('Unknown processing status: ' . $status);
        }

        $log = new IsmLog();
        $log->operation_id = $operationId;
        $log->body = '';
        $log->log_type = $logType;
        $log->save(false);

        return $callbackStatus;
    }

    /**
     * @param array|null $params
     *
     * @return array[]
     */
    private function getOutcomeDocs(array $params = []): array
    {
        $ismParams = $this->getISMParams();
        // получаем все исходящие документы
        $url = $ismParams['api_basepath'] . '/documents/outcome';

        $documents = ['documents' => []];
        $params['count'] = 100;
        for ($i = 0; $i < 10; $i++) {
            $params['start_from'] = $i * 100;
            $answer = $this->sendCurl($url, $params);
            if (empty($answer)) {
                continue;
            }
            $answer = json_decode($answer, true);
            $documents['documents'] = array_merge($documents['documents'], $answer['documents'] ?? []);

            if (count($answer['documents']) < 100) {
                break;
            }
            sleep(2);
        }

        return $documents;
    }

    /**
     * @param array $localDocs
     * @param string|null $dateTime
     *
     * @return array
     */
    private function formedDocIds(array $localDocs, &$dateTime = null): array
    {
        $localDocsIds = [];
        /** @var IsmDoc $document */
        foreach ($localDocs as $document) {
            if (!$dateTime) {
                $dateTime = $document->created;
            }

            $localDocsIds[$document->document_id] = [
                'id'            => $document->id,
                'callbackToken' => $document->callback_token,
                'operationId'   => $document->operation_id,
                'callbackType'  => $document->callback_type,
            ];
        }

        return $localDocsIds;
    }

    /**
     * Забор входящих документов
     *
     * @throws \Exception
     */
    public function checkIncome()
    {
        //запрос последней успешной операции по данному соединению
        $ismDoc = IsmDoc::getLast($this->connectionId)->one();

        $ismParams = $this->getISMParams();
        $params = [
            'filter'     => [
                'doc_status' => 'PROCESSED_DOCUMENT',
                'start_date' => empty($ismDoc) ? date('Y-m-d 00:00:00', time() - 3600 * 24 * 15) : $ismDoc->created,
                'end_date'   => date('Y-m-d H:i:s'),
            ],
            'start_from' => 0,
            'count'      => 15000,
        ];

        $this->authIfNeed();

        $url = $ismParams['api_basepath'] . '/documents/income';
        $documents = ['documents' => []];

        for ($i = 0; $i < 10; $i++) {
            $params['start_from'] = $i * 100;
            $params['count'] = 100;
            $answer = $this->sendCurl($url, $params);
            $answer = json_decode($answer, true);
            $documents['documents'] = array_merge($documents['documents'], $answer['documents']);

            if (count($answer['documents']) < 100) {
                break;
            }

            sleep(2);
        }

        // вытаскиваем id-шники документов, пришедшие из Маркировки
        $incomeDocIds = [];

        foreach ($documents['documents'] as $doc) {
            $incomeDocIds[$doc['document_id']] = $doc;
        }

        $incomeDocIds = array_reverse($incomeDocIds);
        // проверяем, какие из пришедших id-ек уже есть, удаляем из списка
        $existsDocs = IsmDoc::find()->where(['document_id' => array_keys($incomeDocIds), 'income' => true])->all();

        foreach ($existsDocs as $doc) {
            $id = $doc['document_id'];
            unset($incomeDocIds[$id]);
        }

        /**
         * по каждому из оставшихся доков - вынимает тело документа, создаем
         * локальную копию в ism_doc и отправляем документ на api/fns/import
         */
        foreach ($incomeDocIds as $incomeId => $i) {
            $params = [];
            $url = $ismParams['api_basepath'] . '/documents/download/' . $incomeId;

            $docLink = $this->sendCurl($url, $params, 'GET');
            $docLink = json_decode($docLink, true);

            if (!isset($docLink['link'])) {
                throw new \Exception('Error get document link');
            }

            sleep(2);
            $url = $docLink['link'];
            $docBody = $this->sendCurl($url, $params, 'GET');

            try {
                //создаем входящий док, при ошибке пропускаем, получим в след раз.
                Fns::createImport($docBody);

                $ismDoc = new IsmDoc();
                $ismDoc->document_id = $i['document_id'];
                $ismDoc->request_id = $i['request_id'];
                $ismDoc->type = $i['doc_type'];
                $ismDoc->body = $docBody;
                $ismDoc->status = $i['doc_status'];
                $ismDoc->operation_id = null;
                $ismDoc->income = true;
                $ismDoc->connection_id = $this->connectionId;
                $ismDoc->save(false);
            } catch (\Exception $ex) {
            }
        }
    }

    /**
     * @param $documentId
     *
     * @return array
     * @throws \Exception
     */
    public function getDocumentInfo($documentId): array
    {
        $ismParams = $this->getISMParams();
        $params = [];

        $this->authIfNeed();

        $url = $ismParams['api_basepath'] . '/documents/' . $documentId;

        $documents = $this->sendCurl($url, $params, 'GET');

        return json_decode($documents, true);
    }

    /**
     * @param int $docId
     * @param bool $withLink - get data with response link
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    public function getDocumentsTicket($docId, $withLink = true)
    {
        $ismParams = $this->getISMParams();
        $params = [];

        $this->authIfNeed();

        $url = $ismParams['api_basepath'] . '/documents/' . $docId . '/ticket';

        $documents = $this->sendCurl($url, $params, 'GET');
        $documents = json_decode($documents, true);

        if ($withLink && isset($documents['link'])) {
            $documents = $this->getDataTicketByLink($documents['link']);
        }
        sleep(2);

        return $documents;
    }

    /**
     * @param $reqId
     *
     * @return mixed
     * @throws \Exception
     */
    public function getRequestInfo($reqId)
    {
        $params = [];
        $ismParams = $this->getISMParams();

        $this->authIfNeed();

        $url = $ismParams['api_basepath'] . '/documents/request/' . $reqId;
        $documents = $this->sendCurl($url, $params, 'GET');

        return json_decode($documents, true);
    }

    public function isCorrectParams()
    {
        $params = $this->getISMParams();
        if (
            empty($params["api_basepath"]) ||
            empty($params["client_secret"]) ||
            empty($params["client_id"]) ||
            empty($params["user_id"])
        ) {
            return false;
        }

        return true;;
    }

    /**
     * Получение конфигурационных данных ИС Маркировка
     * Данные авторизации, адрес api, etc.
     *
     * @return array
     */
    public function getISMParams()
    {
        return [
            'api_basepath'     => $this->connector->url,
            'client_secret'    => $this->connector->client_secret,
            'client_id'        => $this->connector->client_id,
            'user_id'          => $this->connector->user_id,
            'auth_type'        => $this->connector->auth_type,
            'key_email'        => $this->connector->token_key,
            'key_password'     => $this->connector->token_pass,
            'csptestf_bin'     => !empty($this->connector->cryptopro_path) ? preg_replace(
                    '#\/$#si',
                    '',
                    $this->connector->cryptopro_path
                ) . '/bin/amd64/csptestf' : '/opt/cprocsp/bin/amd64/csptestf',
            'cert_path'        => (preg_match(
                '#api\.sb\.mdlp\.#si',
                $this->connector->url
            )) ? '/root/sb_certnew.cer' : '/root/certnew.cer',
            'docker_container' => 'ssl-gost',
            'token_alg'        => $this->connector->token_alg,
            'sign_mode'        => 'remote', // 'remote' or 'local'
            'sign_remote_ssh'  => $this->connector->sign_remote_ssh,
            'sign_remote_port' => $this->connector->sign_remote_port,
            'curl_mode'        => 'local', // 'local' or 'docker'
            'password'         => $this->connector->auth_type,
        ];
    }

    /**
     * Проверка наличия токена авторизации,
     * авторизация в ИС Маркировка
     *
     * @return bool
     * @throws \Exception
     */
    protected function authIfNeed()
    {
        /**
         * проверяем есть ли токен, актуален ли он (на всякий случай,
         * оставляем запас в 1 минуту на выполнение скрипта)
         */
        if (!$this->token || $this->tokenValidTill < (time() + 60)) {
            // вытягиваем самый свежий токен из базы
            $cachedToken = IsmToken::getLastToken($this->connectionName);
            // если токена нет или он протух (или протухнет через минуту):
            if (!$cachedToken || strtotime($cachedToken->valid_till) < (time() + 60)) {
                // получаем authCode..
                $authCode = $this->getAuthCode();
                // .. и новый токен
                $token = $this->getToken($authCode);
                $this->token = $token['token'];
                $this->tokenValidTill = time() + $token['lifeTime'] * 60;
                $ismToken = new IsmToken();
                $ismToken->token = $this->token;
                $ismToken->valid_till = date('Y-m-d H:i:s', $this->tokenValidTill);
                $ismToken->connection_name = $this->connectionName;
                $ismToken->save(false);

                return true;
            }

            $this->token = $cachedToken->token;
            $this->tokenValidTill = \Yii::$app->formatter->asTimestamp($cachedToken->valid_till);
        }

        return true;
    }

    /**
     * Получение кода авторизации ИС Маркировка
     *
     * @return bool
     * @throws \Exception
     */
    protected function getAuthCode()
    {
        $ismParams = $this->getISMParams();
        $params = [
            'client_secret' => $ismParams['client_secret'],
            'client_id'     => $ismParams['client_id'],
            'user_id'       => $ismParams['user_id'],
            'auth_type'     => $ismParams['auth_type'],
        ];
        $url = $ismParams['api_basepath'] . '/auth';
        $response = $this->sendCurl($url, $params);
        $response = json_decode($response, true);
        $authCode = $response['code'] ?? false;
        if (!$authCode) {
            var_dump($response);
            throw new \Exception('Error get authCode');
        }

        return $authCode;
    }

    /**
     * Получение токена ИС Маркировка
     *
     * @param $authCode
     *
     * @return array
     * @throws \Exception
     */
    protected function getToken($authCode)
    {
        if (!$authCode) {
            throw new \Exception('Get token: No authCode');
        }

        $ismParams = $this->getISMParams();
        if ($ismParams["auth_type"] == self::AUTH_TYPE_SIGNED_CODE) {
            $cryptoProClass = Constant::get('CryptoProClass');
            $fileIdent = date('YmdHis') . '_auth_code_' . sprintf('%1$09d', rand(0, 999999999));
            if (!empty($cryptoProClass) && class_exists($cryptoProClass) && method_exists(
                    $cryptoProClass,
                    'signString'
                )) {
                $s = time();
                do {
                    try {
                        $sign = $cryptoProClass::signString($authCode, $fileIdent, $this->connectionId);
                    } catch (\Exception $ex) {
                        if ($ex->getCode() == 1) {
                            break;
                        }
                    }
                    sleep(1);
                } while (empty($sign) && ($s + 300) > time());

                if (empty($sign)) {
                    throw new \Exception('Ошибка получения подписи');
                }
            } else {
                $sign = CryptoPro::signString($authCode, $fileIdent, $this->connectionId);
            }

            $params = [
                'code'      => $authCode,
                'signature' => base64_encode($sign),
            ];
        } else {
            $params = [
                'code'     => $authCode,
                'password' => $ismParams["password"],
            ];
        }
        $url = $ismParams['api_basepath'] . '/token';
        $response = $this->sendCurl($url, $params);
        $response = json_decode($response, true);
        var_dump($response);
        $token = $response['token'] ?? false;
        $tokenLifeTime = $response['life_time'] ?? false;
        if (!$token || !$tokenLifeTime) {
            throw new \Exception('Error get token');
        }

        return [
            'token'    => $token,
            'lifeTime' => $tokenLifeTime,
        ];
    }

    /**
     * Отправка curl-запроса
     *
     * @param string $url
     * @param array $params
     * @param string $method
     *
     * @return bool|string
     */
    protected function sendCurl($url, $params, $method = 'POST')
    {
        $data = json_encode($params);
        $tmpfname = tempnam(\Yii::getAlias('@runtime'), 'curl');
        file_put_contents($tmpfname, $data);

        $authHeaders = '';
        if ($this->token && $this->tokenValidTill > (time() + 6)) {
            $authHeaders = '-H "Authorization: ' . 'token ' . $this->token . '"';
        }

        $ismParams = $this->getISMParams();
        $certPath = $ismParams['cert_path'];
        $dockerContainer = $ismParams['docker_container'];
        $curlMode = $ismParams['curl_mode'];
        $exec = ('docker' == $curlMode) ? 'sudo docker exec ' . $dockerContainer : '';

        echo $command = $exec . ' curl ' . $url . ' --cacert ' . $certPath . ' -H "Content-Type: application/json" ' . $authHeaders . ' -X ' . $method . (!empty($params) ? ' --data \'@' . $tmpfname . '\'' : '');

        $out = '';
        exec($command, $out);

        echo file_get_contents($tmpfname);
        @unlink($tmpfname);

        return implode("\n", $out);
    }

    protected function putCurl($url, $data, $range = '')
    {
        $tmpfname = tempnam(\Yii::getAlias('@runtime'), 'curl');
        file_put_contents($tmpfname, $data);

        $authHeaders = '';
        if ($this->token && $this->tokenValidTill > (time() + 6)) {
            $authHeaders = '-H "Authorization: ' . 'token ' . $this->token . '"';
        }
        $rangeHeaders = '';
        if (!empty($range)) {
            $rangeHeaders = ' -H "Content-Range: bytes ' . $range . '" ';
        }

        $ismParams = $this->getISMParams();
        $certPath = $ismParams['cert_path'];
        $dockerContainer = $ismParams['docker_container'];
        $curlMode = $ismParams['curl_mode'];
        $exec = ('docker' == $curlMode) ? 'sudo docker exec ' . $dockerContainer : '';

        echo $command = $exec . ' curl ' . $url . ' --cacert ' . $certPath . ' -X PUT -H "Transfer-Encoding: chunked" -H "Content-Type: application/json" ' . $rangeHeaders . $authHeaders . ' -T \'' . $tmpfname . '\'';

        $out = '';
        exec($command, $out);

        @unlink($tmpfname);

        return implode("\n", $out);
    }

    /**
     * Генерация UUID
     *
     * @return string
     */
    protected function genUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for 'time_low'
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for 'time_mid'
            mt_rand(0, 0xffff),
            // 16 bits for 'time_hi_and_version',
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for 'clk_seq_hi_res',
            // 8 bits for 'clk_seq_low',
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for 'node'
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public static function getConnections()
    {
        $connections = [];
        $ucs = UsoConnectors::find()->all();

        foreach ($ucs as $uc) {
            if (count($uc->objects)) {
                $connections[] = $uc->id;
            }
        }

        return $connections;
    }

    /**
     * Получение имени файла для сохранения
     * счетчика неудачных запусков для последующей
     * блокировки запуска
     *
     * @param $method
     *
     * @return string
     */
    protected static function _runFlagGetFileName($method)
    {
        return \Yii::getAlias('@runtime') . '/ism.run.' . $method . '.lock';
    }

    /**
     * Проверка, не было ли ошибок в прошлых запусках
     * Если ошибок 3 и больше - блокируем запуск
     *
     * @param $connection
     *
     * @return bool
     * @throws \Exception
     */
    public static function checkRunAvailable($connection)
    {
        $count = 0;
        $flagFileName = self::_runFlagGetFileName($connection);

        if (file_exists($flagFileName)) {
            $count = file_get_contents($flagFileName);
            $dt = @file_get_contents($flagFileName . '.dt');

            if ((intval($dt) + 3600) < time()) {
                if ($count > 3) {
                    $methodName = explode('-', $connection);
                    $connector = UsoConnectors::findOne(preg_replace('#[^\d]+#si', '', $connection));

                    self::notify($methodName[1], $connector->name, false);
                }

                @unlink($flagFileName);

                return true;
            }
        }

        if ($count >= 3) {
            throw new \Exception(
                sprintf("%d last run failed. Exit.\r\nLast run finished with error. Run disabled.", $count)
            );
        }

        return true;
    }

    /**
     * Сохранение неудачного запуска
     * Увеличение счетчика неудачных пусков
     *
     * @param $connection
     *
     * @return bool
     */
    public static function saveFailedRun($connection)
    {
        $count = 0;
        $flagFileName = self::_runFlagGetFileName($connection);

        if (file_exists($flagFileName)) {
            $count = file_get_contents($flagFileName);
        }

        file_put_contents($flagFileName, $count + 1);

        if ($count == 3) {
            $methodName = explode('-', $connection);
            $connector = UsoConnectors::findOne(preg_replace('#[^\d]+#si', '', $connection));

            self::notify($methodName[1], $connector->name, true);
        }

        return true;
    }

    /**
     * Сохранение удачного запуска
     * Обнуление счетчика неудачных пусков
     *
     * @param $connection
     *
     * @return bool
     */
    public static function saveOkRun($connection)
    {
        $flagFileName = self::_runFlagGetFileName($connection);
        file_put_contents($flagFileName, '0');
        file_put_contents($flagFileName . '.dt', time());

        return true;
    }

    /**
     * Отправка сообщения об ошибках/возобновлении работы сервиса
     *
     * @param string $methodName
     * @param string $connectorName
     * @return void
     * @throws Exception
     */
    protected static function notify($methodName, $connectorName, $isError)
    {
        /** @var Notify $notify */
        $notify = Notify::getAll('FNS-check', null, 'error')->one();

        if (!$notify) {
            return;
        }

        $to = explode(",", $notify->email);

        if (!$to) {
            return;
        }

        $params = pghelper::pgarr2arr($notify->params);
        $message = $params[1];
        $serverName = \Yii::$app->params["monitoring"]["notifyName"] ?? 'Сервер не определен';

        // заполняем актуальные для сообщения переменные
        foreach (Notify::$macros as $macros => $label) {
            if ($macros === '=method=') {
                $message = str_replace('=method=', $methodName, $message);
            } elseif ($macros === '=connector_name=') {
                $message = str_replace('=connector_name=', $connectorName ?? '', $message);
            } elseif ($macros === '=server_name=') {
                $message = str_replace('=server_name=', $serverName ?? '', $message);
            } else {
                $message = str_replace($macros, '', $message);
            }
        }

        if ($isError) {
            $event = 'Метод достиг трех ошибок.';
        } else {
            $event = 'Возобновление отправки сообщений.';
        }
        $message .= PHP_EOL . $event;

        // сохраняем сообщения в notify_log для отправки по крону
        $notify->saveToLog($to, $message, false);

        return;
    }


    /**
     * Получение результата обработки запроса от СУЗ в МДЛП по reportId из СУЗ
     * response:
     * {
     *      "items":[
     *          {
     *              "document_id":"8033d6aa-1ccd-4e76-9e86-c10c522f6201",
     *              "request_id": "ca738a54-37be-4e28-9c39-a55cac2611b1",
     *              "date":"2018-11-16T13:22:06",
     *              "doc_type":10311,
     *              "processing_document_status":"ACCEPTED",
     *              "processed_date":"2018-11-16T13:22:16",
     *              "sgtin_count":2
     *          },
     *          ...
     *      ],
     *      "total": 2
     * }
     *
     * @param string $reportId
     *
     * @return array|null
     * @throws Exception
     */
    public function getInfoBySuzReportId($reportId)
    {
        $ismParams = $this->getISMParams();

        $this->authIfNeed();

        $filters = [
            'filter'     => ['skzkm_report_id' => $reportId],
            'start_from' => 0,
            'count'      => 10,
        ];

        return json_decode(
            $this->sendCurl($ismParams['api_basepath'] . '/documents/skzkm-traces/filter', $filters),
            true
        );
    }
}