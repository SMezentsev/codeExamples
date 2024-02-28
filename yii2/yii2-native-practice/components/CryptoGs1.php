<?php

namespace app\modules\itrack\components;

/**
 * TODO удалить эту прокладку!!!
 */
class CryptoGs1
{
    
    static function closeOrder($orderId, $gtin, $connectionId, $params = [])
    {
        $suz = new Suz($connectionId);
        $suz->closeOrder($orderId, $gtin, "0", $params);
    }
    
    static function sendCodes($gtin, $arr, $connectionId, $ownerId = '', $subjectId = '', $subjectGuid = '', $params = [])
    {
        $suz = new Suz($connectionId);
        
        $ret = $suz->createOrder([
            'gtin'        => $gtin,
            'codes'       => $arr,
            'ownerId'     => $ownerId,
            'subjectId'   => $subjectId,
            'params'      => $params,
            'subjectGuid' => $subjectGuid,
        ]);
        
        return $ret['orderId'];
    }
    
    static function getStatus($orderId, $gtin, $connectionId, $params = [])
    {
        $suz = new Suz($connectionId);
        
        $ret = $suz->orderStatus($orderId, $gtin, $params);
        if ($ret['bufferStatus'] == 'EXHAUSTED' || $ret['bufferStatus'] == 'REJECTED') {
            return ['status' => -1, 'info' => $ret];
        }
        if ($ret['bufferStatus'] == 'ACTIVE' && $ret['leftInBuffer'] == $ret['totalCodes']) {
            return ['status' => 1, 'info' => $ret];
        }
        
        return ['status' => 0, 'info' => $ret];
    }
    
    static function getOrder($orderId, $cnt, $gtin, $connectionId, $params = [])
    {
        $suz = new Suz($connectionId);
        
        $ret = $suz->getOrder($orderId, $gtin, $cnt, $params);
        
        return $ret;
    }
    
}
