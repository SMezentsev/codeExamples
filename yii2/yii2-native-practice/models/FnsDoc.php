<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\CryptoPro;
use Exception;
use Yii;
use app\modules\itrack\models\IsmLog;
use app\modules\itrack\components\XmlService;


class FnsDoc
{
    private $type;
    private $body;
    private $name;
    private $operationId;
    private $callbackToken;
    private $callbackType;

    public function __construct($docParams)
    {
        foreach ($docParams as $name => $paramValue) {
            if (property_exists($this, $name)) {
                $this->$name = $paramValue;
            }
        }
    }

    /**
     * @param string $body
     *
     * @return string
     */
    public static function getTicketStatus(string $body, $operationId): string
    {
        $ticketStatus = IsmDoc::ISM_STATUS_FAILED_READY;
        try {
            $bodyTicketToArray = XmlService::toArray(new \SimpleXMLElement($body));
        } catch (\Exception $ex) {
            return IsmDoc::ISM_STATUS_FAILED;
        }

        $matches = [];
        if (empty($body)) {
            $ticketStatus = IsmDoc::ISM_STATUS_FAILED;
        } else {
            if (preg_match('#kiz_info action_id#si', $body)) {
                $ticketStatus = IsmDoc::ISM_STATUS_PROCESSED;
            } else {
                preg_match('/<operation_result>(.*)<\/operation_result>/si', $body, $matches);
                if (isset($matches[1]) && 'Accepted' == $matches[1]) {
                    $ticketStatus = IsmDoc::ISM_STATUS_PROCESSED;
                } elseif (isset($matches[1]) && 'Partial' == $matches[1]) {
                    $ticketStatus = IsmDoc::ISM_STATUS_PARTED;
                } elseif (isset($matches[1]) && 'Rejected' == $matches[1] && (strpos(
                            $bodyTicketToArray['operation_comment'],
                            'ошибка на этапе подготовки ответа'
                        ) !== false)) {
                    $ticketStatus = IsmDoc::ISM_STATUS_FAILED;
                }
            }
        }

        // Фиксируем в логе данные тикета
        IsmLog::createFromArray(
            [
                'operation_id' => $operationId,
                'body'         => $body,//json_encode($bodyTicketToArray, JSON_UNESCAPED_UNICODE),
                'log_type'     => IsmLog::ISM_LOG_TYPE_RESPONSE_DOC,
            ]
        );

        return $ticketStatus;
    }

    /**
     * Поиск ид операции..
     *
     * @param string $body
     *
     * @return int
     */
    static function getTicketOperationId($body): int
    {
        $operation_id = null;
        /*
         * <?xml version="1.0" encoding="UTF-8" standalone="yes"?><documents version="1.28" xmlns:ns2="http://www.mdlp.org/wsdl/MdlpService.wsdl"><result action_id="200" accept_time="2019-07-01T04:39:05.133+03:00"><operation>441</operation><operation_id>70d2c05e-b44c-49b9-abc4-1eec70869483</operation_id><operation_result>Rejected</operation_result><operation_comment>Операция отклонена</operation_comment><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610E00691T618H</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610E2PM9C88CBE</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610E6482H78CH9</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610E8BMEC348KK</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610EP409246BHA</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610H1EK61PM23A</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610H1CX8321KX3</object_id></errors><errors><error_code>11</error_code><error_desc>Некорректное состояние</error_desc><object_id>0460190700337610H3TAATTAA9X</object_id></errors></result></documents>
         */
        if (preg_match('#<result action_id="200"#si', $body)) {
            if (preg_match('#<operation_id>([^<]+)</operation_id>#si', $body, $match)) {
                $operation_id = $match[1];
            }
        }

        return (int)$operation_id;
    }

    public function __get($name)
    {
        if (!property_exists($this, $name)) {
            throw new Exception('FnsDoc get error: var \'' . $name . '\' dont exists');
        }

        $getter = 'get' . ucfirst($name);
        if (!method_exists($this, $getter)) {
            return $this->$name;
        } else {
            return $this->$getter();
        }
    }

    /**
     * @param $connectionId
     *
     * @return bool|string
     */
    public function getSign($connectionId)
    {
        try {
            $cryptoProClass = Constant::get('CryptoProClass');
            if (!empty($cryptoProClass) && class_exists($cryptoProClass) && method_exists(
                    $cryptoProClass,
                    'signString'
                )) {
                $fileident = "_" . $this->type . "_" . $this->operationId . ".xml";

                $sign = $cryptoProClass::signString($this->body, $fileIdent, $connectionId);
            } else {
                /** @var Идентификатор файла $fileIdent */
                $fileIdent = date('YmdHis') . '_' . $this->type . '_' . sprintf('%1$09d', rand(0, 999999999));

                $sign = CryptoPro::signString($this->body, $fileIdent, $connectionId);
            }
        } catch (Exception $ex) {
            $log = new IsmLog();
            $log->operation_id = $this->operationId;
            $log->body = $ex->getMessage();
            $log->log_type = IsmLog::ISM_LOG_TYPE_GET_ERR;
            $log->save(false);

            throw new Exception($ex->getMessage(), $ex->getCode());
        }

        return $sign;
    }

}