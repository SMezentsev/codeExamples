<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Класс для представления ответа sap ich serialNumberRequest
 * Class IchSapResponse
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class SapIchResponse extends Model
{
    private $rawResponse;
    private $xmlObject;
    private $mockMode;

    public function __construct()
    {
        $this->rawResponse = '';
        $this->xmlObject = null;
        $this->mockMode = false;
    }

    /**
     * Загружает и конвертирует в объект ответ sap ich
     * @param mixed $response
     * @return void
     */
    public function loadResponse($response, bool $mockMode): void
    {
        $this->rawResponse = $response;
        $this->mockMode = $mockMode;

        if ($this->mockMode === true) {
            $this->xmlObject = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $this->rawResponse);
            $this->xmlObject = simplexml_load_string($this->xmlObject);
        }
    }

    /**
     * Возвращает список серийных номеров
     * @return array
     */
    public function getSerialNumbers(): ?array
    {
        if ($this->mockMode) {
            $serialNumbersObject = $this->xmlObject->soapBody->ichsnrSerialNumberConfirmationMessage->SerialNumber;
            if (empty($serialNumbersObject)) {
                throw new \Exception('Ошибка получения кодов от SAP');
            }

            return json_decode(json_encode($serialNumbersObject), true);
        } else {
            return $this->rawResponse['SerialNumber'];
        }
    }
}