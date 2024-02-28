<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\pghelper;
use app\modules\itrack\components\TQS;
use app\modules\itrack\components\boxy\Logger;

/**
 * This is the model class for table "ocs".
 *
 * @property int     $id
 * @property int     $object_uid
 * @property string  $created_at
 * @property int     $created_by
 * @property int     $product_uid
 * @property int     $state
 * @property int     $cnt
 * @property int     $equip_uid
 * @property int     $parent_uid
 * @property int     $article_data
 * @property string  $generations
 * @property string  $info
 *
 * @property Facility $objectU
 * @property Product $productU
 * @property User    $createdBy
 */
class Ocs extends \yii\db\ActiveRecord
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
        return 'ocs';
    }
    
    /**
     * Создание заказа кодов под конкретное оборудование
     *
     * @param type $id
     * @param type $cnt
     * @param type $cntg
     * @param type $cntp
     *
     * @return \app\modules\itrack\models\Generation
     * @throws \yii\web\BadRequestHttpException
     */
    static function createGenerations($id = null, $cnt = null, $cntg = null, $cntp = null)
    {
        $gen_ind = null;
        $ops = self::find()->andWhere(['state' => self::STATE_CREATED])->andWhere(['>=', 'created_at', date('Y-m-d', time() - 3600 * 24 * 30)]);
        if (!empty($id)) {
            $ops->andWhere(['id' => $id]);
        }
        
        $objects = array_keys(\Yii::$app->getModule('sklad')->objectIds);
        
        foreach ($ops->all() as $op) {
            //фича, если оцс на объект у которого свой серв - нельзя генерить генерации здесь...
            if (count($objects) && SERVER_RULE == SERVER_RULE_SKLAD) {
                //склад
                if (!in_array($op->object_uid, $objects)) {
                    if (!empty($id)) {
                        throw new \yii\web\BadRequestHttpException('Создание заказа не на своем сервере');
                    }
                    continue;
                }
            } else {
                //Мастер
                if ($op->object->has_server) {
                    if (!empty($id)) {
                        throw new \yii\web\BadRequestHttpException('Создание заказа не на своем сервере');
                    }
                    continue;
                }
            }
            
            //подсчте резерва на объекте
            $res = Generation::reserveCount($op->object_uid);
            
            $rezerv = [CodeType::CODE_TYPE_INDIVIDUAL => 0, CodeType::CODE_TYPE_GROUP => 0];
            foreach ($res as $g) {
                if ($g['status_uid'] == GenerationStatus::STATUS_READY) {
                    $rezerv[$g['codetype_uid']] += $g['count'];
                }
            }
            //расчет требуемого кол-ва кодов
            if (is_null($cntg)) {
                $cnt_gofra = ceil(ceil($op->cnt / $op->product->nomenclature->cnt) * 1.05);
            } else {
                $cnt_gofra = $cntg;
            }
            if (is_null($cntp)) {
                $cnt_pallet = ceil($cnt_gofra / 4);
            } else {
                $cnt_pallet = $cntp;
            }
            $info = pghelper::pgarr2arr($op->info);
            
            if ($op->equip->type == Equip::TYPE_OCS && $op->equip->data == '1')  //data 1 - только сериализация - групповые не нужны
            {
                $cnt_gofra = $cnt_pallet = 0;
            }
            
            //проваерка резерва
            if ((!isset($info[0]) || empty($info[0])) && ($rezerv[CodeType::CODE_TYPE_INDIVIDUAL] < $op->cnt)) {
                //если заполнено ид - значит есть пярмой заказк на создание генераии и мы не через крон, надо выдать ошибку
                if (!empty($id)) {
                    throw new \yii\web\BadRequestHttpException('Недостаточно резерва индивидуальных кодов (Требуется: ' . $op->cnt . ', Имеется: ' . $rezerv[CodeType::CODE_TYPE_INDIVIDUAL] . ')');
                }
                continue;
            }
            if ((!isset($info[0]) || empty($info[0])) && ($rezerv[CodeType::CODE_TYPE_GROUP] < ($cnt_gofra + $cnt_pallet))) {
                if (!empty($id)) {
                    throw new \yii\web\BadRequestHttpException('Недостаточно резерва групповых кодов (Требуется: п' . $cnt_pallet . ',к' . $cnt_gofra . ', Имеется: ' . $rezerv[CodeType::CODE_TYPE_GROUP] . ')');
                }
                continue;
            }
            
            if (!empty($op->parent_uid)) {
                $pgenerations = pghelper::pgarr2arr($op->parent->generations);
                $pgen_ind = Generation::find()->andWhere(['id' => $pgenerations[0]])->one();
                $pgen_gofra = Generation::find()->andWhere(['id' => $pgenerations[1]])->one();
                $pgen_pallet = Generation::find()->andWhere(['id' => $pgenerations[2]])->one();
            }
            
            //создание генераций
            if ($op->cnt > 0) {
                $gen_ind = new Generation();
                $gen_ind->scenario = 'default';
                $gen_ind->load([
                    'cnt'          => $op->cnt,
                    'codetype_uid' => CodeType::CODE_TYPE_INDIVIDUAL,
                    'capacity'     => \Yii::$app->params['codeGeneration']['capacity'],
                    'prefix'       => \Yii::$app->params['codeGeneration']['prefix'],
                    'product_uid'  => $op->product_uid,
                    'object_uid'   => $op->object_uid,
                    'created_by'   => $op->created_by,
                    'comment'      => empty($op->parent_uid) ? ((!empty($info[0])) ? $info[0] : '') : 'Дозаказ',
                    'status_uid'   => GenerationStatus::STATUS_CREATED,
                    'parent_uid'   => isset($pgen_ind) ? $pgen_ind->id : null,
                    'num'          => empty($info[0]) ? null : $info[0],
                ], '');
                if (!$gen_ind->save(false)) {
                    continue;
                }
//                if(!empty($pgen_ind))
//                {
//                    $pgen_ind->cnt+=$op->cnt;
//                    $pgen_ind->cnt_src+=$op->cnt;
//                    $pgen_ind->save(false);
//                }
            }
            if ($cnt_gofra > 0) {
                $gen_gofra = new Generation();
                $gen_gofra->scenario = 'groupCode';
                $gen_gofra->load([
                    'cnt'          => $cnt_gofra,
                    'codetype_uid' => CodeType::CODE_TYPE_GROUP,
                    'capacity'     => \Yii::$app->params['codeGeneration']['capacity'],
                    'prefix'       => \Yii::$app->params['codeGeneration']['prefix'],
                    'object_uid'   => $op->object_uid,
                    'created_by'   => $op->created_by,
                    'comment'      => empty($op->parent_uid) ? '' : 'Дозаказ',
                    'status_uid'   => GenerationStatus::STATUS_CREATED,
                    'parent_uid'   => isset($pgen_ind) ? $pgen_ind->id : null,
                ], '');
                if (!$gen_gofra->save(false)) {
                    continue;
                }
//                if (!empty($pgen_gofra)) {
//                    $pgen_gofra->cnt += $cnt_gofra;
//                    $pgen_gofra->cnt_src += $cnt_gofra;
//                    $pgen_gofra->save(false);
//                }
            }
            if ($cnt_pallet > 0) {
                $gen_pallet = new Generation();
                $gen_pallet->scenario = 'groupCode';
                $gen_pallet->load([
                    'cnt'          => $cnt_pallet,
                    'codetype_uid' => CodeType::CODE_TYPE_GROUP,
                    'capacity'     => \Yii::$app->params['codeGeneration']['capacity'],
                    'prefix'       => \Yii::$app->params['codeGeneration']['prefix'],
                    'object_uid'   => $op->object_uid,
                    'created_by'   => $op->created_by,
                    'comment'      => empty($op->parent_uid) ? '' : 'Дозаказ',
                    'status_uid'   => GenerationStatus::STATUS_CREATED,
                    'parent_uid'   => isset($pgen_ind) ? $pgen_ind->id : null,
                ], '');
                if (!$gen_pallet->save(false)) {
                    continue;
                }
//                if (!empty($pgen_pallet)) {
//                    $pgen_pallet->cnt += $cnt_pallet;
//                    $pgen_pallet->cnt_src += $cnt_pallet;
//                    $pgen_pallet->save(false);
//                }
            }
            $op->generations = pghelper::arr2pgarr([isset($gen_ind->id) ? $gen_ind->id : null, isset($gen_gofra->id) ? $gen_gofra->id : null, isset($gen_pallet->id) ? $gen_pallet->id : null]);
            $op->state = self::STATE_GENERATE;
            $op->save(false);
            
            if (empty($op->parent_uid) && $op->equip->type == Equip::TYPE_OCS) {
                $params = [];
                $params['generation_uid'] = $gen_ind->id;
                $params['ocs_uid'] = $op->id;
                $params['id'] = $op->id;
                $params['tqs_session'] = '';
                $params['equip_uid'] = $op->equip_uid;
                $params['created_by'] = $op->created_by;
                $params['article-name'] = $gen_ind->product->nomenclature->name;
                FnsOcs::createTQSoutput('get-article-fields-request', $params);
            }
        }
        
        if (isset($gen_ind)) {
            return $gen_ind;
        }
        if (isset($gen_gofra)) {
            return $gen_gofra;
        }
        if (isset($gen_pallet)) {
            return $gen_pallet;
        }
        
        return null;
        
        return $gen_ind ?? $gen_gofra ?? $gen_pallet;
    }
    
    /**
     * Инициирование отправки
     */
    static function sendToEquipment()
    {
        $ops = self::find()->andWhere(['state' => self::STATE_GENERATE])->all();
        foreach ($ops as $op) {
            $generations = pghelper::pgarr2arr($op->generations);
            $ready = true;
            foreach ($generations as $gen) {
                if ($gen) {
                    $g = Generation::find()->andWhere(['id' => $gen])->one();
                    if (empty($g)) {
                        $ready = false;
                    } else {
                        if ($g->status_uid != 3) {
                            $ready = false;
                        }
                    }
                }
            }
            
            if ($ready) {
                $op->state = self::STATE_SEND;
                $op->save(false);
                if (empty($op->parent_uid)) {
                    $op->send();
                } else {
                    $op->sendPush();
                }
            }
        }
    }
    
    /**
     * Обработка полученных данных от OCS
     *
     * @return boolean
     */
    static function ocsData()
    {
        echo 'Обработка OCS' . PHP_EOL;
        $trans = \Yii::$app->db->beginTransaction();
        
        $ocs_data = \Yii::$app->db->createCommand('SELECT * FROM ocs_data WHERE state = 0  ORDER by created_at FOR UPDATE')->queryAll();
        foreach ($ocs_data as $ocs) {
            try {
                $xml = new \SimpleXMLElement($ocs['data']);
                $sns = gzuncompress(base64_decode($xml->response->sns));
                self::log('START ' . date('Y-m-d H:i:s'));
                @mkdir(\Yii::$aliases['@runtime'] . '/ocs');  //сохранение файла на диск..   но он нужен был только при запуске? сейчас наверно не нужен..
                $fname = \Yii::$aliases['@runtime'] . '/ocs/' . $ocs['generation_uid'] . time() . '.txt';
                file_put_contents($fname, $sns);
                self::log('Filename: ' . $fname . '\n');
                
                //сборка идентификаторов генераций участвующих в заказе
                $generations = [];
                $res = \Yii::$app->db->createCommand('SELECT * FROM ocs WHERE :gen=ANY(generations)', [':gen' => $ocs['generation_uid']])->queryOne();
                if (!empty($res)) {
                    $g = pghelper::pgarr2arr($res['generations']);
                    foreach ($g as $v) {
                        if (!empty($v)) {
                            $generations[] = $v;
                        }
                    }
                    $res = \Yii::$app->db->createCommand('SELECT * FROM ocs WHERE parent_uid=:id', [':id' => $res['id']])->queryAll();
                    if (!empty($res)) {
                        foreach ($res as $r) {
                            $g = pghelper::pgarr2arr($r['generations']);
                            foreach ($g as $v) {
                                if (!empty($v)) {
                                    $generations[] = $v;
                                }
                            }
                        }
                    }
                }
                $ocs['generations'] = $generations;
                
                $data = TQS::parseSns($sns);
                
                //внесение распарсенных пар в БД
                $pdo = \Yii::$app->db->getMasterPdo();
                $buf = [];
                foreach ($data as $lvl => $data1) {
                    foreach ($data1 as $code => $parent) {
                        $buf[] = implode("\t", [$ocs['id'], $lvl, $parent, $code]);
                        
                        if (count($buf) > 10000) {
                            $res = $pdo->pgsqlCopyFromArray('ocs_data_pairs', $buf);
                            $buf = [];
                        }
                    }
                }
                if (count($buf)) {
                    $res = $pdo->pgsqlCopyFromArray('ocs_data_pairs', $buf);
                }
                
                $prev = null;
                $olds = \Yii::$app->db->createCommand('SELECT * FROM ocs_data WHERE generation_uid=:gen and created_at<:ct ORDER by created_at desc', [
                    ':gen' => $ocs['generation_uid'],
                    ':ct'  => $ocs['created_at'],
                ])->queryAll();
                foreach ($olds as $old) {
                    if (empty($prev)) {
                        $prev = $old;
                    } else {
                        \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :oid', [':oid' => $old['id']])->execute();
                    }
                }
                
                //обработка ПАР
                TQS::makeGofraNew($ocs, $prev);
                TQS::makePalletaNew($ocs, $prev);
                
                
                //чистим pairs
                //\Yii::$app->db->createCommand("DELETE FROM ocs_data_pairs WHERE ocs_data_id in (SELECT id FROM ocs_data WHERE state=1 and created_at<(current_date - 10))")->execute();
            } catch (\Exception $ex) {
                echo $ex->getMessage() . PHP_EOL;
                echo $ex->getFile() . PHP_EOL;
                echo $ex->getLine() . PHP_EOL;
                throw new \Exception('Ошибка обработки :' . $ex->getMessage());
            }
            \Yii::$app->db->createCommand('UPDATE ocs_data SET state=1 WHERE id = :id', [':id' => $ocs['id']])->execute();
        }
        $trans->commit();
        echo 'Конец обработки OCS' . PHP_EOL;
    }
    
    /**
     * Обработчик
     */
    static function worker()
    {
        self::createGenerations();
        self::sendToEquipment();
        self::ocsData();
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
            [['object_uid', 'created_by', 'product_uid', 'state', 'cnt'], 'default', 'value' => null],
            [['object_uid', 'created_by', 'product_uid', 'state', 'cnt', 'parent_uid'], 'integer'],
            [['created_at'], 'safe'],
            [['generations', 'article_data', 'info'], 'string'],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
            [['product_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Product::class, 'targetAttribute' => ['product_uid' => 'id']],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['created_by' => 'id']],
            [['equip_uid'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['equip_uid' => 'id']],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'object_uid'  => 'Object Uid',
            'created_at'  => 'Created At',
            'created_by'  => 'Created By',
            'product_uid' => 'Product Uid',
            'state'       => 'State',
            'cnt'         => 'Cnt',
            'generations' => 'Generations',
        ];
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
    public function getUser()
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
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_uid']);
    }
    
    /**
     * Генерация запроса для отправки кодов на оборудование
     *
     * @return type
     */
    public function send()
    {
        $generations = pghelper::pgarr2arr($this->generations);
        $gen_ind = Generation::find()->andWhere(['id' => $generations[0]])->one();
        $gen_gofra = Generation::find()->andWhere(['id' => $generations[1]])->one();
        $gen_pallet = Generation::find()->andWhere(['id' => $generations[2]])->one();
        if (!empty($generations[0]) && empty($gen_ind)) {
            return;
        }
        if (!empty($generations[1]) && empty($gen_gofra)) {
            return;
        }
        if (!empty($generations[2]) && empty($gen_pallet)) {
            return;
        }
        
        $article_data = unserialize($this->article_data);
        
        $query = $gen_ind->getCodes();
        $crypto = $codes = [];
        $crypto91 = '';
        foreach ($query->all() as $c) {
            $codes[] = $c['code'];
            $ch = pghelper::pgarr2arr($c['childrens']);
            if (is_array($ch) && !empty($ch[0])) {
                $a = explode('~', $ch[0]);
                $crypto[$c['code']] = ['0' => substr($a[0], 2), '1' => substr($a[1], 2)];
                $crypto91 = substr($a[0], 2);
            }
        }
        if (!empty($crypto)) {
            $params['crypto'] = $crypto;
        }
        $params['codes'] = $codes;
        if (count($params['codes']) != $gen_ind->cnt) {
            return;
        }
        
        if (!empty($gen_gofra)) {
            $query = $gen_gofra->getCodes();
            $codes = [];
            foreach ($query->all() as $c) {
                $codes[] = $c['code'];
            }
            $params['gcodes'] = $codes;
            if (count($params['gcodes']) != $gen_gofra->cnt) {
                return;
            }
        }
        
        if (!empty($gen_pallet)) {
            $query = $gen_pallet->getCodes();
            $codes = [];
            foreach ($query->all() as $c) {
                $codes[] = $c['code'];
            }
            $params['pcodes'] = $codes;
            if (count($params['pcodes']) != $gen_pallet->cnt) {
                return;
            }
        }
        
        $expdate = explode(' ', $gen_ind->product->expdate_full);
        if (strlen($expdate[2]) > 2) {
            $expdate[2] = substr($expdate[2], 2);
        }
//var_dump($model->product->components);
        $cdate = $gen_ind->product->components[0]['cdate'];
        $acdate = explode('-', preg_replace('#\s.*$#si','',$cdate));
//var_dump($cdate,$acdate);
        $params['order-name'] = $gen_ind->object_uid . '/' . $gen_ind->num;
        $params['article-name'] = $gen_ind->product->nomenclature->name;
        $params['id'] = $this->id;
        if (empty($article_data)) {
            $params['fields'] = [
                '[01] GTIN (N14)'                  => $gen_ind->product->nomenclature->gtin,
                '[11] Production Date (N6)'        => substr($acdate[0], 2) . $acdate[1] . $acdate[2],
                //            "[12] Due Date" => $acdate[2] . $acdate[1] . substr($acdate[0], 2),
                '[17] Expiration Date (N6)'        => $expdate[2] . $expdate[1] . $expdate[0],
                '[10] Batch or Lot Number (X..20)' => $gen_ind->product->series,
            ];
            $params['fields2'] = [
                '[10] Batch or Lot Number (X..20)'         => $gen_ind->product->series,
                '[01] GTIN (N14)'                          => $gen_ind->product->nomenclature->gtin,
                '[02] GTIN of Contained Trade Items (N14)' => $gen_ind->product->nomenclature->gtin,
                '[11] Production Date (N6)'                => substr($acdate[0], 2) . $acdate[1] . $acdate[2],
                '[17] Expiration Date (N6)'                => $expdate[2] . $expdate[1] . $expdate[0],
            ];
            $params['fields3'] = [
                '[10] Batch or Lot Number (X..20)' => $gen_ind->product->series,
                '[01] GTIN (N14)'                  => $gen_ind->product->nomenclature->gtin,
                '[17] Expiration Date (N6)'        => $expdate[2] . $expdate[1] . $expdate[0],
            ];
        } else {
            foreach ($article_data as $lvl => $vv) {
                $params['fields' . ($lvl ? $lvl : '')] = [];
                foreach ($article_data[$lvl] as $field) {
                    $v = '';
                    switch ($field) {
                        case '[01] GTIN (N14)':
                            $v = $gen_ind->product->nomenclature->gtin;
                            break;
                        case '[02] GTIN of Contained Trade Items (N14)':
                            $v = $gen_ind->product->nomenclature->gtin;
                            break;
                        case '[10] Batch or Lot Number (X..20)':
                            $v = $gen_ind->product->series;
                            break;
                        case '[11] Production Date (N6)':
                            $v = substr($acdate[0], 2) . $acdate[1] . $acdate[2];
                            break;
                        case '[17] Expiration Date (N6)':
                            $v = $expdate[2] . $expdate[1] . $expdate[0];
                            break;
                        case '[91] Company Internal Information (X..30)':
                            $v = $crypto91;
                            break;
                    }
                    $params['fields' . ($lvl ? $lvl : '')][$field] = $v;
                }
            }
        }
        
        if ($this->equip->type == Equip::TYPE_OCS) {
            $params['generation_uid'] = $gen_ind->id;
            $params['ocs_uid'] = $this->id;
            $params['tqs_session'] = "";
            $params['equip_uid'] = $this->equip_uid;
            $params['created_by'] = $this->created_by;
            FnsOcs::createTQSoutput('create-order', $params);
        } elseif ($this->equip->type == Equip::TYPE_OCS_RUS) {
            $params['equip_uid'] = $this->equip_uid;
            echo 'SEND TO OCS RUS\n';
            try {
                $ocsrus = new equipment\OcsRus($this->equip->ip);
                $res = $ocsrus->createOrder($params);
                $err = $res->response->err ?? '';
                if (!empty($err)) {
                    throw new \Exception($err);
                }
                //фиксируем успех отправки заказа
                if (!empty($gen_ind)) {
                    $gen_ind->comment = $res->response->res ?? '';
                    $gen_ind->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                    $gen_ind->save(false);
                }
                if (!empty($gen_gofra)) {
                    $gen_gofra->comment = $res->response->res ?? '';
                    $gen_gofra->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                    $gen_gofra->save(false);
                }
                if (!empty($gen_pallet)) {
                    $gen_pallet->comment = $res->response->res ?? '';
                    $gen_pallet->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                    $gen_pallet->save(false);
                }
            } catch (\Exception $ex) {
                //ошибка отправки заказа - фиксируем ошибку в генерации
                echo $ex->getMessage() . PHP_EOL;
                if (!empty($gen_ind)) {
                    $gen_ind->comment = $ex->getMessage();
                    $gen_ind->status_uid = GenerationStatus::STATUS_DECLINED;
                    $gen_ind->save(false);
                }
                if (!empty($gen_gofra)) {
                    $gen_gofra->comment = $ex->getMessage();
                    $gen_gofra->status_uid = GenerationStatus::STATUS_DECLINED;
                    $gen_gofra->save(false);
                }
                if (!empty($gen_pallet)) {
                    $gen_pallet->comment = $ex->getMessage();
                    $gen_pallet->status_uid = GenerationStatus::STATUS_DECLINED;
                    $gen_pallet->save(false);
                }
            }
        }
    }
    
    /**
     * Создание запроса на дозаказ кодов
     *
     * @return type
     */
    public function sendPush()
    {
        $generations = pghelper::pgarr2arr($this->generations);
        $gen_ind = Generation::find()->andWhere(['id' => $generations[0]])->one();
        $gen_gofra = Generation::find()->andWhere(['id' => $generations[1]])->one();
        $gen_pallet = Generation::find()->andWhere(['id' => $generations[2]])->one();
        if (empty($gen_ind) && empty($gen_gofra) && empty($gen_pallet)) {
            return;
        }
        
        $pgenerations = pghelper::pgarr2arr($this->parent->generations);
        $pgen_ind = Generation::find()->andWhere(['id' => $pgenerations[0]])->one();
        
        $codes = [];
        if (!empty($gen_ind)) {
            $query = $gen_ind->getCodes();
            $crypto = $codes = [];
            foreach ($query->all() as $c) {
                $codes[] = $c['code'];
                $ch = pghelper::pgarr2arr($c['childrens']);
                if (is_array($ch) && !empty($ch[0])) {
                    $a = explode('~', $ch[0]);
                    $crypto[$c['code']] = ['0' => substr($a[0], 2), '1' => substr($a[1], 2)];
                }
            }
            if (!empty($crypto)) {
                $params['crypto'] = $crypto;
            }
        }
        $params['codes'] = $codes;
        
        $codes = [];
        if (!empty($gen_gofra)) {
            $query = $gen_gofra->getCodes();
            foreach ($query->all() as $c) {
                $codes[] = $c['code'];
            }
            if (count($codes) != $gen_gofra->cnt) {
                return;
            }
        }
        $params['gcodes'] = $codes;
        
        $codes = [];
        if (!empty($gen_pallet)) {
            $query = $gen_pallet->getCodes();
            foreach ($query->all() as $c) {
                $codes[] = $c['code'];
            }
            if (count($codes) != $gen_pallet->cnt) {
                return;
            }
        }
        $params['pcodes'] = $codes;
        
        $params['order-name'] = $pgen_ind->object_uid . '/' . $pgen_ind->num;
        $params['id'] = $this->id;
        //$params['generation_uid'] = $gen_ind->id;
        $params['ocs_uid'] = $this->id;
        $params['tqs_session'] = '';
        $params['equip_uid'] = $this->equip_uid;
        $params['created_by'] = $this->created_by;
        FnsOcs::createTQSoutput('push-serial-numbers-request', $params);
    }
    
    /**
     * Лог обработки SNS файлов
     * @param type $message
     * @param type $level
     */
    static private function log($message, $level = Logger::LEVEL_INFO) {
        \Yii::getLogger()->log($message, $level, 'sns');
    }

}
