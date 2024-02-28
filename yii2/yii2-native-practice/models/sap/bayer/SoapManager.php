<?php


namespace app\modules\itrack\models\sap\bayer;

use yii\base\Model;

class SoapManager extends Model
{
    public function SerialNumberRequest($data)
    {
        try {
            $serialNumberRequestMessage = new SerialNumberRequestMessage($data);
            
            $orderConductor = new BayerOrderConductor($serialNumberRequestMessage);
            $orderConductor->createOrder();
            
            $serialNumberConfirmationMessage = new SerialNumberConfirmationMessage($serialNumberRequestMessage);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            exit;
            $serialNumberConfirmationMessage = new SerialNumberConfirmationMessage($serialNumberRequestMessage, true);
        }
        
        return $serialNumberConfirmationMessage->build();
    }
}