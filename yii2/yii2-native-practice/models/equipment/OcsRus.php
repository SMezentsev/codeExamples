<?php

namespace app\modules\itrack\models\equipment;

use yii\base\Model;

/**
 * Адаптер для коммуникации с оборудованием OCS-RUS
 * Class SapAdapter
 *
 * @package app\modules\itrack\models\sap
 */
class OcsRus extends Model
{
    
    public  $debug = true;
    private $wsdl;
    private $soap;
    
    public function __construct(string $wsdl)
    {
        $this->wsdl = $wsdl;
        
        try {
            $this->soap = new \SoapClient($this->wsdl, [
                'location'   => $this->wsdl,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ]);
        } catch (\SoapFault $ex) {
            $this->soap = null;
            throw new \Exception('Нет подключения к Soap серверу (' . $wsdl . '): ' . $ex->getMessage());
        }
    }
    
    /**
     * Отправка запроса на оборудование
     *
     * @param array $data
     *
     * @return boolean
     */
    public function createOrder(array $data)
    {
        try {
            $request = $this->createRequestOrderCreate($data);
            
            if ($this->debug) {
                file_put_contents("runtime/logs/ocs_rus.log", print_r($request, true), FILE_APPEND);
            }
            
            $result = $this->soap->OrderCreate(['request' => $request]);
            
            if ($this->debug) {
                file_put_contents("runtime/logs/ocs_rus.log", print_r($result, true), FILE_APPEND);
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Формирование запроса для создания заказа
     *
     * @param type $data
     *
     * @return array
     */
    public function createRequestOrderCreate($data)
    {
        $request = [];
        $request["type"] = 'CEMLabeling';
        $request["article_gtin"] = $data["fields"]["[01] GTIN (N14)"];
        $request["batch_number"] = $data["fields"]["[10] Batch or Lot Number (X..20)"];
        $request["expiryDate"] = "20" . substr($data["fields"]["[17] Expiration Date (N6)"], 0, 2) . "-" . substr($data["fields"]["[17] Expiration Date (N6)"], 2, 2) . "-" . substr($data["fields"]["[17] Expiration Date (N6)"], 4, 2);
        $request["productionDate"] = "20" . substr($data["fields"]["[11] Production Date (N6)"], 0, 2) . "-" . substr($data["fields"]["[11] Production Date (N6)"], 2, 2) . "-" . substr($data["fields"]["[11] Production Date (N6)"], 4, 2);
        $request["quantity"] = count($data["codes"]);
        $request["line_uid"] = $data["equip_uid"];
        $request["product"] = [];
        
        if (is_array($data["codes"])) {
            foreach ($data["codes"] as $code) {
                $ai = [];
                if (isset($data["crypto"][$code])) {
                    $ai[] = ['key' => 91, 'val' => $data["crypto"][$code]["0"]];
                    $ai[] = ['key' => 92, 'val' => $data["crypto"][$code]["1"]];
                }
                $request["product"][] = ['sn' => $code, 'ag' => 0, 'ai' => $ai];
            }
        }
        
        if (is_array($data["gcodes"])) {
            foreach ($data["gcodes"] as $code) {
                $request["product"][] = ['sn' => $code, 'ag' => 2];
            }
        }
        
        if (is_array($data["pcodes"])) {
            foreach ($data["pcodes"] as $code) {
                $request["product"][] = ['sn' => $code, 'ag' => 3];
            }
        }
        
        return $request;
    }
}
