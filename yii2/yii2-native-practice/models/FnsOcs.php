<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\pghelper;
use linslin\yii2\curl;

/**
 * This is the model class for table "fns".
 *
 * @property integer  $id
 * @property string   $created_at
 * @property string   $operation_uid
 * @property integer  $state
 * @property string   $answerfns
 * @property string   $data
 * @property string   $codes
 * @property string   $note
 * @property integer  $product_uid
 * @property integer  $object_uid
 * @property integer  $user_uid
 * @property string   $invoice_uid
 * @property boolean  $internal
 * @property boolean  $is_uploaded
 * @property integer  $queue
 * @property boolean  $regen
 * @property boolean  $replaced
 * @property boolean  $upd
 * @property string   $uploaded_at
 * @property integer  $prev_uid
 *
 * @property Invoice  $invoice
 * @property Facility $object
 * @property Product  $product
 * @property User     $user
 */
class FnsOcs extends Fns
{
    static $file_prefix = 'fnsOcs';
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'operations_ocs';
    }
    
    static function find()
    {
        $query = parent::find();
        $query->select(new \yii\db\Expression("operations_ocs.*,case when operations_ocs.upd THEN operations_ocs.updated_at ELSE operations_ocs.created_at + operations_ocs.created_time end as cdt,is_paleta(operations_ocs.code_flag) as paleta"));
        $query->Where(new \yii\db\Expression("operations_ocs.dbschema = get_constant('schema')"));
        
        return $query;
    }
    
    /**
     * Отправка подготовленных документов по TQS протоколу на OCS оборудование
     *
     * отправка через коннектор, делаем заявку, коннектор скачивает и отправляет
     * baseUrl для коннектора
     */
    static function sendDocs()
    {
        $fns = \Yii::$app->params['TQS']['url'];
        if (!empty(\Yii::$app->params['TQS']['baseUrl'])) {
            \Yii::$app->urlManager->baseUrl = \Yii::$app->params['TQS']['baseUrl'];
        }
        $trans = \Yii::$app->db->beginTransaction();
        
        echo "отправка TQS" . PHP_EOL;
        $op = \Yii::$app->db->createCommand("SELECT operations_ocs.*,users.fio,users.email,users.params FROM operations_ocs "
            . " LEFT JOIN users ON (operations_ocs.created_by = users.id)"
            . " WHERE operations_ocs.internal=true and operations_ocs.state=:st and operations_ocs.created_at>=(current_date - 2) FOR UPDATE of operations_ocs", [
            ":st" => Fns::STATE_TQS_RECEIVED,
        ])->queryAll();
        foreach ($op as $operation) {
            echo "\t" . $operation["id"] . PHP_EOL;
            
            $ret = [];
            $a = pghelper::pgarr2arr($operation["data"]);
            try {
                if (is_array($a)) {
                    $ret = unserialize($a[0]);
                }
                $equip_uid = $ret["equip_uid"];
                $equip = \Yii::$app->db->createCommand("SELECT * FROM users WHERE id=:id", [':id' => $equip_uid])->queryOne();
                $ret = unserialize($equip["params"]);
                $con = \Yii::$app->db->createCommand("SELECT * FROM ocs_connectors WHERE id=:id", [':id' => $ret["ip"]])->queryOne();
            } catch (\Exception $ex) {
            }
            
            if (!empty($con)) {
                $cfns = $con["url"] . '/api/v1/export';
            } else {
                $cfns = $fns;
            }
            
            $params = [
                "id"       => $operation["id"],
                "url"      => \Yii::$app->urlManager->createAbsoluteUrl(['itrack/fns/download', 'id' => $operation["id"], 'type' => 'ocs', 'tok' => md5($operation["created_at"] . $operation["created_time"] . $operation["id"])]),
                "callback" => \yii\helpers\Url::toRoute(['fns/' . $operation["id"], 'tok' => md5($operation["created_at"] . $operation["created_time"] . $operation["id"]), 'type' => 'ocs']),
            ];
///////////
///гвоздь TODO conf
            $params["url"] = str_replace("http://192.168.58.252", "http://10.102.1.90", $params["url"]);
            $params["callback"] = str_replace("http://192.168.58.252", "http://10.102.1.90", $params["callback"]);
//////////            
            
            $curl = new curl\Curl();
            $response = $curl->setRequestBody(json_encode($params))
                ->setHeaders([
                    'Content-Type'   => 'application/json',
                    'Content-Length' => strlen(json_encode($params)),
                ])
                ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                ->setOption(CURLOPT_TIMEOUT, 120)
                ->post($cfns);
            
            
            if ($curl->responseCode == 200) {
                \Yii::$app->db->createCommand("UPDATE operations_ocs SET state=:state,sended_at=now() WHERE id = :id", [
                    ":state" => Fns::STATE_TQS_COMPLETED,
                    ":id"    => $operation["id"],
                ])->execute();
            } else {
                var_dump($response);
            }
            $curl->reset();
        }
        $trans->commit();
    }
    
    /**
     * Отправка подготовленных документов по TQS протоколу на OCS оборудование
     *
     * отправка через коннектор, делаем заявку, коннектор скачивает и отправляет
     * baseUrl для коннектора
     */
    static function sendDocsNew()
    {
        $rootUser = \app\modules\itrack\models\User::findByLogin('root');
        echo "отправка TQS" . PHP_EOL;
        $op = \Yii::$app->db->createCommand("SELECT operations_ocs.*,users.fio,users.email,users.params FROM operations_ocs "
            . " LEFT JOIN users ON (operations_ocs.created_by = users.id)"
            . " WHERE operations_ocs.internal=true and operations_ocs.state=:st and operations_ocs.created_at=current_date and (operations_ocs.created_at + operations_ocs.created_time)>(now()-interval '60 minutes') ORDER by created_at,created_time FOR UPDATE of operations_ocs", [
            ":st" => Fns::STATE_TQS_RECEIVED,
        ])->queryAll();
        foreach ($op as $operation) {
            echo "\t" . $operation["id"] . PHP_EOL;
            
            $ret = [];
            $a = pghelper::pgarr2arr($operation["data"]);
            try {
                if (is_array($a)) {
                    $ret = unserialize($a[0]);
                }
                $equip_uid = $ret["equip_uid"];
                $equip = \Yii::$app->db->createCommand("SELECT * FROM users WHERE id=:id", [':id' => $equip_uid])->queryOne();
                $ret = unserialize($equip["params"]);
                //$con = \Yii::$app->db->createCommand("SELECT * FROM ocs_connectors WHERE id=:id", [':id' => $ret["ip"]])->queryOne();
                $con = $ret["ip"];
            } catch (\Exception $ex) {
                $con = preg_replace('#^http://#si', '', \Yii::$app->params["TQS"]["url"] ?? "");
            }
            
            if (!empty($con)) {
                $connect_data = explode(':', $con);
            } else {
                echo "Unknown remote addr" . PHP_EOL;
                continue;
            }
            
            $fns = self::findOne($operation["id"]);
            $body = file_get_contents($fns->getFileName());
            if (empty($body)) {
                echo "Unknown request $operation[id]" . PHP_EOL;
                continue;
            }
            
            //отправка
            try {
                $tqsClient = new \app\modules\itrack\components\TQSclient($connect_data[0], $connect_data[1]);
                $tqsClient->send($body);
//                $fns->state = Fns::STATE_TQS_COMPLETED;
                $fns->sended_at = new \yii\db\Expression('now()');
                
                
                //ответ с таймаутом
                $answer = $tqsClient->receive();
                
                $xml = new \SimpleXMLElement($answer);
                $retvalue = (string)$xml->response->result->{"return-value"} ?? "unknown";
                if ($retvalue == "ok") {
                    $fns->state = Fns::STATE_TQS_CONFIRMED;
                } else {
                    $fns->state = Fns::STATE_TQS_DECLAINED;
                }

//                $result = FnsOcs::createTQSinput([
//                            "created_by" => $rootUser->id,
//                            "body" => $answer,
//                            "userid" => 0,
//                ]);
                
                
                $fns->fnsid = 'TQS';
                $fns->fns_log = $answer;
                $fns->save(false);  ///
                
                $fns->parseTQSanswer(['fns_log' => $answer]);
                $fns->makeNotify();
            } catch (\Exception $ex) {
                echo "Ошибка отправки документа: " . $ex->getMessage() . PHP_EOL;
                $fns_data = pghelper::pgarr2arr($fns->data);
                if (is_array($fns_data)) {
                    $fns_data = unserialize($fns_data[0]);
                }
                if (isset($fns_data["tqs_session"]) && !empty($fns_data["tqs_session"])) {
                    $ts = TqsSession::findOne($fns_data["tqs_session"]);
                    $ts->nerrors++;
                    if ($ts->nerrors >= 6) {
                        $ts->state = -1;
                    }
                    $ts->save(false);
                }
            }
        }
    }
    
    /**
     * если не получили ответ от оборудования через 30 минут - вешаем флаг таймаут
     */
    static function checkUnansweredTqs()
    {
        $res = \Yii::$app->db->createCommand("SELECT *,case when ((created_at+created_time)+ interval ' 30 minutes')<now() then true else false end old FROM operations_ocs WHERE created_at>current_date - 3 and state=:state and operation_uid=:op", [
            ":state" => Fns::STATE_TQS_COMPLETED,
            ":op"    => Fns::OPERATION_TQS_INP,
        ])->queryAll();
        foreach ($res as $op) {
            if ($op["old"]) {
                $ret = [];
                $a = pghelper::pgarr2arr($op["data"]);
                try {
                    if (is_array($a)) {
                        $ret = unserialize($a[0]);
                    }
                } catch (\Exception $ex) {
                }
                if ($ret["type"] == "create-order") {
                    $genid = $ret["generation"];
                    if (!empty($genid)) {
                        $gen = Generation::find()->andWhere(["id" => $genid])->one();
                        if (!empty($gen)) {
                            if ($gen->status_uid == GenerationStatus::STATUS_READY) {
                                $gen->status_uid = GenerationStatus::STATUS_TIMEOUT;
                                $gen->save(false);
                            }
                        }
                    }
                }
            }
        }
    }
    
}
