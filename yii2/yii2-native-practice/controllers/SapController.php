<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\models\Sicpa;

class SapController extends ActiveController
{
//    use ControllerTrait;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'sap',
    ];
    
    public $modelClass = 'app\modules\itrack\models\Sicpa';
    
    public function actionJobs()
    {
        $params = \Yii::$app->request->getBodyParams();
        
        try {
            $data = date('d.m.Y H:i:s', time()) . ' Получен запрос от sap...' . PHP_EOL;
            $data .= PHP_EOL;
            $data .= json_encode(\Yii::$app->request->post()) . PHP_EOL;
            $data .= PHP_EOL;
            
            $file = fopen(\Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'sap.log', 'a+');
            $data .= date('d.m.Y H:i:s', time()) . ' Произведена запись в лог' . PHP_EOL;
            
            $data .= PHP_EOL;
            
            fwrite($file, $data);
            fclose($file);
            
            $ip_arr = \Yii::$app->params["sicpa"]["access"] ?? [];
            if (!empty($ip_arr) && !empty(\Yii::$app->request->remoteIP) && !in_array(\Yii::$app->request->remoteIP, $ip_arr)) {
                throw new \Exception('Access denied');
            }
            
            $sicpa = Sicpa::findOne(['id' => $params["id"]]);
            if (empty($sicpa)) {
                throw new \Exception('Order not found (id = ' . $params["id"] . ')');
            }
            
            $status = $params["jobStatus"] ?? 'Status empty';
            if (method_exists($sicpa, $status)) {
                $sicpa->$status($params);
            } else {
                throw new \Exception('Unknown status: ' . $status);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payload' => [],
                'error'   => $e->getMessage(),
            ];
        }
        
        return [
            'success' => true,
            'payload' => [],
            'error'   => '',
        ];
    }
    
    public function actionOrder($id, $cnt)
    {
    }
}