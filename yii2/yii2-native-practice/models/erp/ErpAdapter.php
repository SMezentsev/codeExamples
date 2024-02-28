<?php


namespace app\modules\itrack\models\erp;

use SoapClient;
use yii\base\Model;

/**
 * Класс обертка для удобной работы с эндпоинтами API 1с
 * Class ErpAdapter
 *
 * @package app\modules\itrack\models\erp
 */
class ErpAdapter extends Model
{
    
    private $client;
    
    public function __construct()
    {
        $this->prepareConnectObject();
    }
    
    /**
     * Метод вызывает iTrack после окончания упаковки на
     * производственной линии определенной партии продукта
     *
     * @param $orderId
     * @param $packingStatus
     *
     * @return mixed
     */
    public function packingCompleted($orderId, $packingStatus)
    {
        $params = [
            'order_id'       => $orderId,
            'packing_status' => $packingStatus,
        ];
        
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Завершение упаковки отправка уведомления в erp", [
            ["field" => "Id заказа", "value" => $orderId],
        ]);
        
        return $this->client->packaging_completed($params);
    }
    
    /**
     * Метод вызывает iTrack после окончания регистрации партии продукта
     *
     * @param $orderId
     * @param $registrationStatus
     *
     * @return mixed
     */
    public function registrationCompleted($orderId, $registrationStatus)
    {
        $params = [
            'order_id'            => $orderId,
            'registration_status' => $registrationStatus,
        ];
        
        return $this->client->registration_completed($params);
    }
    
    /**
     * Создает объект подключения к API для дальнейшего вызова
     *
     * @throws \SoapFault
     */
    private function prepareConnectObject()
    {
        error_reporting(E_ERROR);
        ini_set("soap.wsdl_cache_enabled", "0");
        
        $this->client = new SoapClient(\Yii::$app->params['erp1cSettings']['wsdlUrl'], [
                'login'        => \Yii::$app->params['erp1cSettings']['login'],
                'password'     => \Yii::$app->params['erp1cSettings']['password'],
                'soap_version' => SOAP_1_2,
                'cache_wsdl'   => WSDL_CACHE_NONE,
                'trace'        => true,
                'features'     => SOAP_USE_XSI_ARRAY_TYPE,
            ]
        );
    }
}