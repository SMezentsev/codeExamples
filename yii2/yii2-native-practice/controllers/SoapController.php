<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\models\sap\bayer\SoapLogger;
use app\modules\itrack\models\sap\bayer\SoapManager;

class SoapController extends ActiveController
{
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'sap',
    ];
    
    public $modelClass = 'app\modules\itrack\models\Soap';
    
    public function actionExch()
    {
        $server = new \SoapServer("http://itrack-rf-api.dev-og.com/sap.wsdl");
        $soapLog = new SoapLogger();
        $server->setClass(SoapManager::class);
        $soapLog->writeLog();
        $server->handle();
    }
}
