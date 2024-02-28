<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use Yii;
use yii\base\Model;
use app\modules\itrack\models\sap\skopinpharm\SapIchResponse;

/**
 * Класс для создания запросов в sap ich
 * Class SapIchConnector
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class SapIchConnector extends Model
{
    private $wsdl;
    private $certificate;
    private $soapClient;
    private $sendingSystem;
    private $senderGln;
    private $receiverGln;
    private $sapIchResponse;

    const MOCK_MODE = false;

    public function __construct(SapIchResponse $sapIchResponse)
    {
        $this->wsdl = Yii::$app->params['sapIchSettings']['wsdl'];
        $this->sendingSystem = Yii::$app->params['sapIchSettings']['sendingSystem'];
        $this->senderGln = Yii::$app->params['sapIchSettings']['senderGln'];
        $this->receiverGln = Yii::$app->params['sapIchSettings']['receiverGln'];
        $this->certificate = $this->getCertificate();
        $this->soapClient = $this->getSoapClient();
        $this->sapIchResponse = $sapIchResponse;
    }

    /**
     * Делает запрос в sap ich на получение серийных кодов
     * @param int $codesCount
     * @param string $gtin
     * @return \app\modules\itrack\models\sap\skopinpharm\SapIchResponse
     * @throws \ErrorException
     */
    public function serialNumberRequest(int $codesCount, string $gtin): SapIchResponse
    {
        $requestData = $this->getSerialNumberRequestData($codesCount, $gtin);
        $request = new \SoapVar($requestData, XSD_ANYXML);

        try {
            if (self::MOCK_MODE === false) {
                $response = $this->soapClient->SerialNumberRequest($request);
            } else {
                $response = $this->getMockResponse();
            }

        } catch (\Exception $e) {
            throw new \ErrorException($e->getMessage() . ' ' . $this->soapClient->__getLastResponse());
        }

        $this->sapIchResponse->loadResponse($response, self::MOCK_MODE);

        return $this->sapIchResponse;
    }

    /**
     * Отправляет Epcis в сап
     * @param string $reportData
     * @return string
     */
    public function epcisReportRequest(string $reportData) :string
    {
        $reportData = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '',$reportData);
        $request = new \SoapVar($reportData, XSD_ANYXML);
        $response = $this->soapClient->EPCISDocument($request);

        return json_encode($response);
    }

    /**
     * Возвращает запрос для получения кодов из sap ich
     * @param int $codesCount
     * @param string $gtin
     * @return string
     */
    private function getSerialNumberRequestData(int $codesCount, string $gtin): string
    {
        return "<ns0:SerialNumberRequestMessage xmlns:ns0=\"http://sap.com/xi/SAPICH\">
			<SendingSystem>$this->sendingSystem</SendingSystem>
			<IDType>GTIN</IDType>
			<Size>$codesCount</Size>
			<ObjectKey>
				<Name>GTIN</Name>
				<Value>$gtin</Value>
			</ObjectKey>
			<ObjectKey>
				<Name>LIST_RANGE</Name>
				<Value>L</Value>
			</ObjectKey>
			<ObjectKey>
				<Name>SENDER_GLN</Name>
				<Value>$this->senderGln</Value>
			</ObjectKey>
			<ObjectKey>
				<Name>RECEIVER_GLN</Name>
				<Value>$this->receiverGln</Value>
			</ObjectKey>
		</ns0:SerialNumberRequestMessage>";
    }

    private function getMockResponse()
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Header />
            <soap:Body>
                <ichsnr:SerialNumberConfirmationMessage xmlns:ichsnr="http://sap.com/xi/SAPICH">
                    <ReceivingSystem>4610012029997</ReceivingSystem>
                    <IDType>GTIN</IDType>
                    <ActionCode>C</ActionCode>
                    <Size>15</Size>
                    <ObjectKey>
                        <Name>GTIN</Name>
                        <Value>04610012020604</Value>
                    </ObjectKey>
                    <ObjectKey>
                        <Name>LIST_RANGE</Name>
                        <Value>L</Value>
                    </ObjectKey>
                    <ObjectKey>
                        <Name>SENDER_GLN</Name>
                        <Value>7612790000752</Value>
                    </ObjectKey>
                    <ObjectKey>
                        <Name>RECEIVER_GLN</Name>
                        <Value>4610012029997</Value>
                    </ObjectKey>
                    <SerialNumber>2964642659669</SerialNumber>
                    <SerialNumber>7002162250872</SerialNumber>
                    <SerialNumber>3869324428269</SerialNumber>
                    <SerialNumber>3909006801777</SerialNumber>
                    <SerialNumber>5823930960191</SerialNumber>
                    <SerialNumber>2936179177035</SerialNumber>
                    <SerialNumber>8257473809615</SerialNumber>
                    <SerialNumber>5460197873696</SerialNumber>
                    <SerialNumber>2682123656802</SerialNumber>
                    <SerialNumber>4646336135077</SerialNumber>
                    <SerialNumber>7373233365587</SerialNumber>
                    <SerialNumber>4902419454133</SerialNumber>
                    <SerialNumber>8992366148416</SerialNumber>
                    <SerialNumber>7745136775618</SerialNumber>
                    <SerialNumber>4159180768378</SerialNumber>
                </ichsnr:SerialNumberConfirmationMessage>
            </soap:Body>
        </soap:Envelope>';
    }

    /**
     * Получает сертификат из папки указанной в конфиге
     * @return string
     * @throws \ErrorException
     */
    private function getCertificate(): string
    {
        $certificateDir = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR
            . Yii::$app->params['sapIchSettings']['certificatesFolder'];

        if (!is_dir($certificateDir)) {
            throw new \ErrorException('Директория с сертификатами sap ich не найдена!');
        }

        $certificates = scandir($certificateDir);
        $sapCertificateName = array_pop($certificates);
        $certificate = $certificateDir . DIRECTORY_SEPARATOR . $sapCertificateName;

        return $certificate;
    }

    /**
     * @return \SoapClient
     * @throws \SoapFault
     */
    private function getSoapClient(): \SoapClient
    {
        return new \SoapClient(null, [
            'exceptions' => 1,
            'location' => $this->wsdl,
            'uri' => '',
            'trace' => true,
            'local_cert' => $this->certificate,
            'passphrase' => '',
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
            'authentication' => SOAP_AUTHENTICATION_DIGEST,
            "soap_version"  => SOAP_1_1,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]),
        ]);
    }
}