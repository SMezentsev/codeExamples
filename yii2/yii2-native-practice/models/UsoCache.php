<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use app\modules\itrack\components\ISMarkirovka;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use app\modules\itrack\components\pghelper;
use Yii;
use Exception;

class UsoCache extends ActiveRecord
{
    const STATE_NEW = 0;
    const STATE_SENDED = 1;
    const STATE_RECEIVED = 2;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'uso_cache';
    }

/**
 * УСТАРЕЛО используем FullHierarchy
 *  Парсинг иерархии по АПИ МДЛП
 * @param type $codes
 * @param ISMarkirovka $ism
 * @return type
 * @throws \Exception
 */    
    public static function parseHierarchy($codes, ISMarkirovka $ism)
    {
        $r = [];
        foreach ($codes as $code) {
            sleep(5);
            $res = $ism->codeHierarchy($code);
            $lr = [];
            if (is_array($res)) {
                if (is_array($res['down'])) {
                    foreach ($res['down'] as $d) {
                        if ($d['sscc'] != $code) {
                            $lr[] = $d['sscc'];
                            $r[] = ['sscc' => $d['sscc'], 'parent_sscc' => $code];
                        }
                    }
                } else {
                    throw new \Exception('');
                }
            }
            if (empty($lr)) {
                //потомков нет, запрашиваем индивидуальные
                $offset = 0;
                $limit = 100;
                do {
                    sleep(5);
                    $ccc = 0;
                    $res = $ism->codeInfo($code, $offset, $limit);
                    if (is_array($res) && isset($res['entries'])) {
                        foreach ($res['entries'] as $entr) {
                            $ccc++;
                            $r[] = [
                                'sgtin'           => $entr['sgtin'],
                                'status'          => $entr['status'],
                                'gtin'            => $entr['gtin'],
                                'series_number'   => $entr['batch'],
                                'expiration_date' => Yii::$app->formatter->asDate($entr['expiration_date'], 'php:d.m.Y'),
                                'parent_sscc'     => $code,
                            ];
                        }
                    } else {
                        throw new \Exception('');
                    }
                    $offset += $limit;
                    /**
                     * в ЦРПТ пока косяк скачет сортировка при многостраничных запросах
                     * 8.4.2.
                     * обращение №SR00302676.
                     */
//                    if ($ccc == $limit) {
//                        throw new \Exception('В коробе более 100 упаковок - парим через 210');
//                    }
                } while ($ccc == $limit);
            } else {
                $lrr = self::parseHierarchy($lr, $ism);
                $r = array_merge($r, $lrr);
            }
        }
        
        return $r;
    }
    
/**
 * рекурсивный сбор иерархии
 * @param type $res
 * @param array $r
 */
    static function getInfo(array $res, string $code, array &$r) {
        foreach ($res as $d) {
            if (isset($d['sscc'])) {
                if ($d['sscc'] != $code)
                    $r[] = ['sscc' => $d['sscc'], 'parent_sscc' => $code];
                if (isset($d['childs'])) {
                    self::getInfo($d['childs'], $d['sscc'], $r);
                }
            }
            if (isset($d['sgtin'])) {
                $r[] = [
                    'sgtin' => $d['sgtin'],
                    'status' => $d['internal_state'],
                    'gtin' => $d['gtin'],
                    'series_number' => $d['batch'],
                    'expiration_date' => Yii::$app->formatter->asDate($d['expiration_date'], 'php:d.m.Y'),
                    'parent_sscc' => $code,
                ];
            }
        }
    }

    
/**
 * Парсинг иерархии по АПИ МДЛП - методов 8.4.3 - одним запросом всю иерархию
 * @param type $codes
 * @param ISMarkirovka $ism
 * @return type
 * @throws \Exception
 */    
    public static function parseFullHierarchy($codes, ISMarkirovka $ism)
    {
        $r = [];
        foreach ($codes as $code) {
            sleep(31);
            $res = $ism->codeFullHierarchy($code);
            if (is_array($res)) {
                if (isset($res['down']) && is_array($res['down'])) {
                    if(isset($res['down']['childs']))
                    {
                        self::getInfo($res['down']['childs'], $res['down']['sscc'], $r);
                    }
                    else
                    {
                        throw new Exception('Ошибка sscc: ' . $code . ' - не имеет потомков');
                    }
                } else {
                    throw new Exception('Ошибка обработки: ' . $code);
                }
            }
            else
                throw new Exception('Некорректный ответ на запрос иерархии по коду: ' . $code);
            
        }
        
        return $r;
    }
    
    /**
     * Отправка 210 запросов в УСО
     *
     * @param int $connectionId
     *
     * @throws \yii\db\Exception
     */
    static function send2USO($connectionId)
    {
        echo 'USO_CACHE - отправка дополнительных запросов 210 в УСО' . PHP_EOL;

        $operations = self::find()
                        ->leftJoin('objects', 'objects.id = uso_cache.object_uid')
                        ->andWhere(['state' => self::STATE_NEW])
                        ->andWhere(new \yii\db\Expression('objects.uso_uid=:id OR objects.uso_uid_out=:id'), [':id' => $connectionId])
                        ->all();

        /** @var UsoCache $operation */
        foreach ($operations as $operation) {
            $trans = \Yii::$app->db->beginTransaction();
            
            echo 'uso id: ' . $operation->id . ' - ' . $connectionId . PHP_EOL;
            try {
                $ism = new ISMarkirovka($connectionId);
                if ($operation->codetype_uid == CodeType::CODE_TYPE_GROUP) 
                {
                    try {
                        $ret = self::parseFullHierarchy([$operation->code], $ism);
                        if (!empty($ret)) {
                            echo 'Данные получены по АПИ' . PHP_EOL;
                            //данные собраны, сохраняем
                            $operation->state = self::STATE_RECEIVED;
                            $operation->answer = serialize($ret);
                            $operation->save();
                            
                            $gtins = $series = [];
                            $indcnt = $grpcnt = 0;
                            foreach ($ret as $retv) {
                                if (isset($retv['series_number']) && isset($retv['gtin'])) {
                                    $gtins[$retv['gtin']] = 1;
                                    $series[$retv['series_number']] = 1;
                                    $indcnt++;
                                } else {
                                    $grpcnt++;
                                }
                            }
                            //создаем 211 документ для истории
                            $fns211 = Fns::createDoc([
                                        'created_by' => 0,
                                        'state' => Fns::STATE_COMPLETED,
                                        'code' => $operation->code,
                                        'operation_uid' => Fns::OPERATION_211,
                                        'data' => pghelper::arr2pgarr(array_merge(array_keys($gtins), array_keys($series))),
                                        'fnsid' => '211',
                                        'codes_data' => pghelper::arr2pgarr([json_encode(['grp' => $operation->code, 'codes' => []])]),
                                        'code_flag' => 1,
                                        'indcnt' => $indcnt,
                                        'grpcnt' => $grpcnt,
                                        'prev_uid' => $operation->operation_uid,
                            ]);

                            file_put_contents($fns211->getFileName(), print_r($ret, true));
                            
                            $trans->commit();
                            continue;
                        }
                    } catch (\Exception $ex) {
                        //не получилось собрать по апи, отправляем 210
                        echo 'api error: ' . $ex->getMessage() . PHP_EOL;
                    }
                }
                if ($operation->codetype_uid == CodeType::CODE_TYPE_INDIVIDUAL) { // SGTIN
                    try {
                        sleep(1);
                        $ret = $ism->getDetalSgtinInfo($operation->code);
                        if (!empty($ret) && isset($ret['sgtin_info'])) {
                            $ret = [
                                'gtin' => $ret['sgtin_info']['gtin'],
                                'series_number' => $ret['sgtin_info']['batch'],
                                'expiration_date' => Yii::$app->formatter->asDate($ret['sgtin_info']['expiration_date'], 'php:d.m.Y'),
                                'status' => $ret['sgtin_info']['status'],
                                'parent_code' => $ret['sgtin_info']['pack3_id'],
                            ];
                            echo 'Данные получены по АПИ' . PHP_EOL;
                            //данные собраны, сохраняем
                            $operation->state = self::STATE_RECEIVED;
                            $operation->answer = serialize($ret);
                            $operation->save();

                            //создаем 211 документ для истории
                            $fns211 = Fns::createDoc([
                                        'created_by' => 0,
                                        'state' => Fns::STATE_COMPLETED,
                                        'code' => $operation->code,
                                        'operation_uid' => Fns::OPERATION_211,
                                        'data' => pghelper::arr2pgarr(array_merge([$ret['gtin']], [$ret['series_number']])),
                                        'fnsid' => '211',
                                        'codes_data' => pghelper::arr2pgarr([json_encode(['grp' => '', 'codes' => [$operation->code]])]),
                                        'code_flag' => 1,
                                        'indcnt' => 1,
                                        'grpcnt' => 0,
                                        'prev_uid' => $operation->operation_uid,
                                            ]
                            );

                            file_put_contents($fns211->getFileName(), print_r($ret, true));

                            $trans->commit();
                            continue;
                        }
                    } catch (Exception $ex) {
                        //не получилось собрать по апи, отправляем 210
                        echo 'api error: ' . $ex->getMessage() . PHP_EOL;
                    }
                }
                echo 'Запрос 210' . PHP_EOL;
                $docBody = file_get_contents(\Yii::$app->urlManager->createAbsoluteUrl(['itrack/fns/download', 'type' => 210, 'id' => $operation->id, 'tok' => md5($operation->cdate . $operation->id)]));
                $doc = new FnsDoc([
                    'body'          => $docBody,
                    'type'          => $operation->fnsid ?? '210',
                    'operationId'   => $operation->id,
                    'callbackToken' => md5($operation->cdate . $operation->id),
                    'callbackType'  => 'usoCache',
                ]);
                
                $ism->sendDoc($doc);

                $operation->setAttributes([
                    'state' => self::STATE_SENDED,
                    'sended_at' => new Expression('now()')
                ]);
                $operation->save();
            } catch (Exception $e) {
            }
            
            $trans->commit();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['cdate', 'code', 'answer'], 'string'],
            [['state', 'operation_uid', 'codetype_uid', 'object_uid'], 'integer'],
            [['operation_uid', 'codetype_uid', 'code'], 'required'],
            [['sended_at'], 'safe'],
            [['operation_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Fns::class, 'targetAttribute' => ['operation_uid' => 'id']],
            //[['codetype_uid'], 'exist', 'skipOnError' => true, 'targetClass' => CodeType::class, 'targetAttribute' => ['codetype_uid' => 'id']],
        ];
    }
    
    public function scenarios()
    {
        return ['default' => ['code', 'codetype_uid', 'operation_uid', 'object_uid', 'state', 'sended_at']];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOp()
    {
        return $this->hasOne(Fns::class, ['operation_uid' => 'id']);
    }
    
    /**
     * Обработка ответа Маркировки
     *
     * @return $this
     * @throws BadRequestHttpException
     */
    public function answer($params)
    {
        if (md5($this->cdate . $this->id) != $params['tok']) {
            throw new BadRequestHttpException('Ошибка, доступа к файлу: ' . $this->cdate . '/' . $this->id . '/' . md5($this->cdate . $this->id) . '/' . $params['tok']);
        }
        if ($this->state == 1) {
            if (preg_match('#kiz_info action_id#si', $params['fns_log'])) {
                try {
                    Fns::createImport($params['fns_log']);
                } catch (\Exception $ex) {
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Сброс статуса для переотправки в фнс
     */
    public function reSend()
    {
        $this->state = self::STATE_NEW;
        $this->save(false);
    }

    /**
     * @param string $interval
     *
     * @return array
     *
     * @throws \yii\db\Exception
     */
    public static function findUnsent(string $interval)
    {
        return Yii::$app->db->createCommand('
                SELECT * FROM uso_cache 
                WHERE sended_at > now() - interval :interval AND state = 1
            ', [':interval' => $interval]
        )->queryAll();
    }

}
