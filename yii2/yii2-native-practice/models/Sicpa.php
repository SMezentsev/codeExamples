<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\pghelper;
use linslin\yii2\curl;

/**
 * This is the model class for table "sicpa".
 *
 * @property int      $id
 * @property int      $object_uid
 * @property string   $created_at
 * @property int      $created_by
 * @property int      $product_uid
 * @property int      $state
 * @property int      $cnt
 * @property string   $generations
 * @property int      $equip_uid
 * @property string   $article_data
 * @property int      $parent_uid
 * @property string   $info
 *
 * @property Facility $object
 * @property Product  $product
 * @property User     $createdBy
 */
class Sicpa extends \yii\db\ActiveRecord
{
    const STATE_CREATED = 0;
    const STATE_GENERATE = 1;
    const STATE_SEND = 2;
    const STATE_OTHER = 3;
    
    static $auditOperation = AuditOperation::OP_EQUIP;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sicpa';
    }
    
    /**
     * Отправка запросов на оборудование
     */
    static function sending()
    {
        $sicpas = self::findAll(['state' => Sicpa::STATE_GENERATE]);
        
        foreach ($sicpas as $sicpa) {
            $generations = pghelper::pgarr2arr($sicpa->generations);
            $ready = true;
            
            foreach ($generations as $gen) {
                $generation = Generation::findOne(['id' => $gen]);
                
                if (!empty($generation) && $generation->status_uid != GenerationStatus::STATUS_READY) {
                    $ready = false;
                }
            }
            
            if ($ready) {
                $sicpa->sendToEquip();
            }
        }
    }
    
    /**
     * Создание заказа кодов по заявке сикпы
     *
     * @param int $id
     *
     * @return \app\modules\itrack\models\Generation
     */
    static function createGenerations($id = null)
    {
        $gen_ind = null;
        $ops = self::find()->andWhere(["state" => self::STATE_CREATED])->andWhere(['>=', 'created_at', date("Y-m-d", time() - 3600 * 24 * 30)]);
        if (!empty($id)) {
            $ops->andWhere(['id' => $id]);
        }
        
        $objects = array_keys(\Yii::$app->getModule('sklad')->objectIds);
        
        foreach ($ops->all() as $op) {
            //фича, если оцс на объект у которого свой серв - нельзя генерить генерации здесь...
            if (count($objects) && SERVER_RULE == SERVER_RULE_SKLAD) {
                //склад
                if (!in_array($op->object_uid, $objects)) {
                    continue;
                }
            } else {
                //Мастер
                if ($op->object->has_server) {
                    continue;
                }
            }
            
            //подсчте резерва на объекте
            $res = Generation::reserveCount($op->object_uid);
            
            $rezerv = [CodeType::CODE_TYPE_INDIVIDUAL => 0, CodeType::CODE_TYPE_GROUP => 0];
            foreach ($res as $g) {
                if ($g["status_uid"] == GenerationStatus::STATUS_READY) {
                    $rezerv[$g["codetype_uid"]] += $g["count"];
                }
            }
            
            
            //проваерка резерва
            if ((!isset($info[0]) || empty($info[0])) && ($rezerv[CodeType::CODE_TYPE_INDIVIDUAL] < $op->cnt)) {
                continue;
            }
            
            //создание генераций
            if ($op->cnt > 0) {
                $icnt = $op->cnt;
                //if($icnt < 1000)$icnt = 1000;
                $gen_ind = new Generation();
                $gen_ind->scenario = 'default';
                $gen_ind->load([
                    'cnt'          => $icnt,
                    'codetype_uid' => CodeType::CODE_TYPE_INDIVIDUAL,
                    'capacity'     => \Yii::$app->params["codeGeneration"]["capacity"],
                    'prefix'       => \Yii::$app->params["codeGeneration"]["prefix"],
                    'product_uid'  => $op->product_uid,
                    'object_uid'   => $op->object_uid,
                    'created_by'   => $op->created_by,
                    'comment'      => '',
                    'status_uid'   => GenerationStatus::STATUS_CREATED,
                    'parent_uid'   => null,
                    'num'          => null,
                ], '');
                if (!$gen_ind->save(false)) {
                    continue;
                }
            }
            $op->generations = pghelper::arr2pgarr([isset($gen_ind->id) ? $gen_ind->id : null, isset($gen_gofra->id) ? $gen_gofra->id : null, isset($gen_pallet->id) ? $gen_pallet->id : null]);
            $op->state = self::STATE_GENERATE;
            $op->save(false);
        }
        
        if (isset($gen_ind)) {
            return $gen_ind;
        }
        
        return null;
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['object_uid', 'created_by', 'product_uid', 'equip_uid'], 'required'],
            [['object_uid', 'created_by', 'product_uid', 'state', 'cnt', 'equip_uid', 'parent_uid'], 'default', 'value' => null],
            [['object_uid', 'created_by', 'product_uid', 'state', 'cnt', 'equip_uid', 'parent_uid'], 'integer'],
            [['created_at'], 'safe'],
            [['generations', 'article_data', 'info'], 'string'],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
            [['product_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Product::class, 'targetAttribute' => ['product_uid' => 'id']],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['created_by' => 'id']],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'object_uid'   => 'Object Uid',
            'created_at'   => 'Created At',
            'created_by'   => 'Created By',
            'product_uid'  => 'Product Uid',
            'state'        => 'State',
            'cnt'          => 'Cnt',
            'generations'  => 'Generations',
            'equip_uid'    => 'Equip Uid',
            'article_data' => 'Article Data',
            'parent_uid'   => 'Parent Uid',
            'info'         => 'Info',
        ];
    }
    
    /**
     * Отправко кодов на оборудование
     *
     * @throws \Exception
     */
    public function sendToEquip()
    {
        try {
            $product = $this->product;
            
            if (empty($product)) {
                throw new \Exception('Не найдена товарная карта');
            }
            
            $generations = pghelper::pgarr2arr($this->generations);
            $codes = [];
            $gens = [];
            
            foreach ($generations as $generation) {
                if (!empty($generation)) {//
                    $gcodes = \Yii::$app->db->createCommand("SELECT codes.* FROM _get_codes('generation_uid='':generationUid''') as codes",
                        [':generationUid' => $generation])->queryAll();
                    
                    foreach ($gcodes as $row) {
                        $childs = pghelper::pgarr2arr($row["childrens"]);
                        
                        if (count($childs) && !empty($childs[0])) {
                            //gs1
//                            $codes[] = str_replace('~1',chr(29), '01' . $product->nomenclature->gtin . '21' . $row["code"] . '~1' . $childs[0]);
                            
                            $parts = explode('~', $childs[0], 2);
                            $parts[0] = preg_replace('#^(91)(.*)#', '[$1]$2', $parts[0]);
                            $parts[1] = preg_replace('#^(92)(.*)#', '[$1]$2', $parts[1]);
                            $codes[] = '[21]' . /*$product->nomenclature->gtin . */
                                $row["code"] . implode("", $parts);
                        } else {
                            $codes[] = $row["code"];
                        }
                    }
                    $g = Generation::findOne(['id' => $generation]);
                    
                    if (!empty($g)) {
                        $gens[] = $g;
                    }
                }
            }
            
            $equip = $this->equip;
            
            if (empty($equip)) {
                throw new \Exception('Не найдено оборудование');
            }
            
            
            function dt($str)
            {
                $ar = explode(" ", $str);
                
                if (count($ar) == 2 || count($ar) > 3) {
                    $dt = "$ar[1]-$ar[0]-01";
                } else {
                    $dt = "$ar[2]-$ar[1]-$ar[0]";
                }
                
                return date('Y-m-d\TH:i:s\Z', \Yii::$app->formatter->asTimestamp($dt));
            }
            
            $params = \Yii::$app->params["sicpa"] ?? ['enable' => false];
            
            if ($params['enable']) {
                $curl = new curl\Curl;
                $request = json_encode([
                    'id'                => $this->id,
                    'quantity'          => $this->cnt,
                    'lineNum'           => $equip->login,//'rpharm-LINE01',//
                    'gtin'              => $product->nomenclature->gtin,
                    'lotNo'             => $product->series,
                    'addProdInfo'       => $product->nomenclature->tnved,
                    'expDate'           => dt($product->expdate_full),// исо формат!!
                    'prodDate'          => dt($product->cdate),
                    'labelFields'       => [['fieldName' => 'productName', 'fieldData' => $product->nomenclature->name]],
                    'productNumbers'    => $codes,
                    'serializationType' => 'SERIALIZATION',
                    'aggregationType'   => 'NONE',
                ]);
                //var_dump($request, $equip->ip);
                $response = $curl->setRawPostData($request)
                    ->setHeaders([
                        'Content-Type'   => 'application/json',
                        'Content-Length' => strlen($request),
                        'Content-MD5'    => base64_encode(md5($request, true)),
                        'Authorization'  => 'Basic ' . base64_encode('setme:setme'),
                        'Expect'         => '',
                    ])
                    ->setOption(CURLOPT_SSL_VERIFYPEER, false)
                    //->setOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC)
                    //->setOption(CURLOPT_USERPWD, 'setme:setme')md
                    ->setOption(CURLOPT_TIMEOUT, 600)
                    ->post($equip->ip . 'production-orders');
                
                file_put_contents(\Yii::$aliases["@runtime"] . '/logs/sicpla.log',
                    'answer: ' . date("Y-m-d H:i:s>") . $curl->responseCode . '=' . $curl->getRequestHeaders() . $response . PHP_EOL . PHP_EOL,
                    FILE_APPEND);
                if (in_array($curl->responseCode, [200, 201])) {
                    $this->state = self::STATE_SEND;
                    $this->save(false);
                    
                    foreach ($gens as $generation) {
                        $generation->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                        $generation->save(false, ['status_uid']);
                    }
                } else {
                    //var_dump($curl->responseCode, $curl->getRequestHeaders());
                    var_dump($response);
                    $response = json_decode($response, true);
                    $err = $response["msg"] ?? 'Неизвестная ошибка';
                    
                    foreach ($gens as $generation) {
                        $generation->status_uid = GenerationStatus::STATUS_DECLINED;
                        $generation->comment = $err;
                        $generation->save(false, ['status_uid', 'comment']);
                    }
                    
                    $this->state = self::STATE_OTHER;
                    $this->save(false);
                }
            }
        } catch (\Exception $ex) {
            echo $ex->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEquip()
    {
        return $this->hasOne(Equip::class, ['id' => 'equip_uid']);
    }
    
    
    /**
     * обработка запроса от оборудования
     */
    public function CREATED($params)
    {
        //информация
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
    
    public function READY($params)
    {
        //информация
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
    
    public function CANCELLED($params)
    {
        //отменено финал
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
    
    public function STARTED($params)
    {
        //информация
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
    
    public function RELEASED($params)
    {
        //выполнено финал
        //сохраняем статус генерации
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
        
        //обработка данных
        $sampleNumbers = $params["sampleNumbers"];          //у нас нет отбора - игнор
        $readyBox = $params["readyBox"];                    //данные агрегации - игнорим
        $serializedOnly = $params["serializedOnly"] ?? [];  //--коды рпошедшие сериализацию!!!!
        $defectiveCodes = $params["defectiveCodes"];        //коды ушли в брак - обрабатываем!!!
        $emptyNumbers = $params["emptyNumbers"];            //сериализованы - но не использованы  в упаковке... игнорим!!!
        
        $codes = [];
        foreach ($defectiveCodes as $cc) {
            $codes[] = $cc["number"];
        }
        
        Code::brak($codes);
    }
    
    public function FAILED($params)
    {
        //ошибка финал
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
    
    public function REJECTED($params)
    {
        //отклонено финал
        $generations = pghelper::pgarr2arr($this->generations);
        $gen = Generation::findOne(['id' => $generations[0]]);
        
        if (!empty($gen)) {
            $gen->comment = $gen->comment . "<br/>\r\n" . sprintf("с %s по %s - статус: %s", $params["startTime"], $params["endTime"], $params["jobStatus"]);
            $gen->status_uid = GenerationStatus::STATUS_CONFIRMEDREPORT;
            $gen->save(false, ['comment', 'status_uid']);
        }
    }
}
