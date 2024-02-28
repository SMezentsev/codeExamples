<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 03.06.15
 * Time: 10:38
 */

namespace app\modules\itrack\components;

use yii\base\Component;
use Yii;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Ocs;
use app\modules\itrack\components\boxy\Logger;

class TQS extends Component
{
    
    static $version   = '1.15';
    static $agentName = 'iTrack';
    
    static function generate(string $operation, array $params)
    {
        switch ($operation) {
            case 'push-serial-numbers-request':
                return self::PushSerialNumbers($params);
                break;
            case 'create-order':
                return self::CreateOrder($params);
                break;
            case 'get-order-status-request':
                return self::GetOrderStatus($params);
                break;
            case 'query-serial-numbers-request':
                return self::SerialsRequest($params);
                break;
            case 'get-article-fields-request':
                return self::ArticleFieldsRequest($params);
                break;
        }
        
        return false;
    }
    
    static function PushSerialNumbers(array $params)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tnt version="' . self::$version . '" xmlns="http://www.wipotec.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.wipotec.com qr_tnt_requests.xsd"></tnt>');
        self::addHeader($xml);
        
        $request = $xml->addChild('request');
        $request->addAttribute('xsi:type', 'push-serial-numbers-request');
        $request->addAttribute('version', '1.0');
        $request->addAttribute('id', $params['id'] . rand(11111111, 9999999999) . '-' . rand(11111111, 9999999999));
        
        $request->addChild('order-name', $params['order-name']);
        
        $data = '';
        if (isset($params['codes']) && count($params['codes'])) {
            //$data .= "<lvl><id>0</id><sn><no>" . implode("</no> </sn> <sn> <no>", $params["codes"]) . "</no></sn></lvl>";
            $data .= '<lvl><id>0</id>';
            foreach ($params['codes'] as $co) {
                if (isset($params['crypto'][$co])) {
                    $data .= '<sn><no>' . $co . '</no><associated-data>';
                    foreach ($params['crypto'][$co] as $vid => $viv) {
                        $data .= '<value id="' . $vid . '">'.$viv.'</value>';
                    }
                    $data .= '</associated-data></sn>';
                } else {
                    $data .= '<sn><no>' . $co . '</no></sn>';
                }
            }
            
            $data .= '</lvl>';
        }
        if (isset($params['gcodes']) && count($params['gcodes'])) {
            $data .= '<lvl><id>2</id><sn><no>' . implode('</no> </sn> <sn> <no>', $params['gcodes']) . '</no></sn></lvl>';
        }
        if (isset($params['pcodes']) && count($params['pcodes'])) {
            $data .= '<lvl><id>3</id><sn><no>' . implode('</no> </sn> <sn> <no>', $params['pcodes']) . '</no></sn></lvl>';
        }
        $sns = $request->addChild('sns', base64_encode(gzcompress($data)));
        $sns->addAttribute('compression', 'DEFLATE');
        $sns->addAttribute('uncompressed-length', strlen($data));
        
        return $xml;
    }
    
    static function SerialsRequest(array $params)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tnt version="' . self::$version . '" xmlns="http://www.wipotec.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.wipotec.com qr_tnt_requests.xsd"></tnt>');
        self::addHeader($xml);
        
        $request = $xml->addChild('request');
        $request->addAttribute('xsi:type', 'query-serial-numbers-request');
        $request->addAttribute('version', '1.0');
        $request->addAttribute('id', $params['id'] . rand(11111111, 9999999999) . '-' . rand(11111111, 9999999999));
        
        $request->addChild('order-name', $params['order-name']);
        if (isset($params['aggregation-level-id'])) {
            $request->addChild('aggregation-level-id', $params['aggregation-level-id']);
        }
//        else
//            $request->addChild ('aggregation-level-id');
        
        if (isset($params['sn-status'])) {
            $request->addChild('sn-status', $params['sn-status']);
        }
//        else
//            $request->addChild('sn-status');
        if (isset($params['sn-flags'])) {
            $ch = $request->addChild('sn-flags');
            $ch->addChild('required-flags', $params['sn-flags']);
            $ch->addChild('allowed-flags', $params['sn-flags']);
        } else {
            $request->addChild('sn-flags');
        }
        
        $request->addChild('include-status');
        $request->addChild('include-additional-data');
        $request->addChild('include-child-sns');
        
        return $xml;
    }
    
    static function GetOrderStatus(array $params)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tnt version="' . self::$version . '" xmlns="http://www.wipotec.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.wipotec.com qr_tnt_requests.xsd"></tnt>');
        self::addHeader($xml);
        
        $request = $xml->addChild('request');
        $request->addAttribute('xsi:type', 'get-order-status-request');
        $request->addAttribute('version', '1.0');
        $request->addAttribute('id', $params["id"] . rand(11111111, 9999999999) . '-' . rand(11111111, 9999999999));
        
        $request->addChild('order-name', $params['order-name']);
        
        return $xml;
    }
    
    static function ArticleFieldsRequest(array $params)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tnt version="' . self::$version . '" xmlns="http://www.wipotec.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.wipotec.com qr_tnt_requests.xsd"></tnt>');
        self::addHeader($xml);
        
        $request = $xml->addChild('request');
        $request->addAttribute('xsi:type', 'get-article-fields-request');
        $request->addAttribute('version', '1.0');
        $request->addAttribute('id', $params["id"] . rand(11111111, 9999999999) . '-' . rand(11111111, 9999999999));
        
        $request->addChild('article-name', $params['article-name']);
        $request->addChild('get-all-data');
        
        return $xml;
    }
    
    static function CreateOrder(array $params)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tnt version="' . self::$version . '" xmlns="http://www.wipotec.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.wipotec.com qr_tnt_requests.xsd"></tnt>');
        self::addHeader($xml);
        
        $request = $xml->addChild('request');
        $request->addAttribute('xsi:type', 'create-order-request');
        $request->addAttribute('version', '1.0');
        $request->addAttribute('id', $params["id"] . rand(11111111, 9999999999) . '-' . rand(11111111, 9999999999));
        
        $request->addChild('order-name', $params['order-name']);
        $request->addChild('article-name', $params['article-name']);
        $odata = $request->addChild('order-data');
        $aglevel = $odata->addChild('aggregation-level');
        $aglevel->addChild('id', '0');
        $dfields = $aglevel->addChild('data-field-values');
        foreach ($params['fields'] as $key => $value) {
            $el = $dfields->addChild('element');
            $el->addChild('name', $key);
            $el->addChild('value', $value);
        }
        if (isset($params['fields2']) && is_array($params['fields2'])) {
            $aglevel2 = $odata->addChild('aggregation-level');
            $aglevel2->addChild('id', '2');
            $dfields = $aglevel2->addChild('data-field-values');
            foreach ($params['fields2'] as $key => $value) {
                $el = $dfields->addChild('element');
                $el->addChild('name', $key);
                $el->addChild('value', $value);
            }
        }
        if (isset($params['fields3']) && is_array($params['fields3'])) {
            $aglevel3 = $odata->addChild('aggregation-level');
            $aglevel3->addChild('id', '3');
            $dfields = $aglevel3->addChild('data-field-values');
            foreach ($params['fields3'] as $key => $value) {
                $el = $dfields->addChild('element');
                $el->addChild('name', $key);
                $el->addChild('value', $value);
            }
        }

//        $data = "<lvl><id>0</id><sn><no>".implode("</no> </sn> <sn> <no>",$params["codes"])."</no></sn></lvl>";
        $data = '<lvl><id>0</id>';
        foreach ($params['codes'] as $co) {
            if (isset($params['crypto'][$co])) {
                $data .= '<sn><no>' . $co . '</no><associated-data>';
                foreach ($params['crypto'][$co] as $vid => $viv) {
                    $data .= '<value id="' . $vid . '">'.$viv.'</value>';
                }
                $data .= '</associated-data></sn>';
            } else {
                $data .= '<sn><no>' . $co . '</no></sn>';
            }
        }
        
        $data .= '</lvl>';
        
        if (isset($params['gcodes'])) {
            $data .= '<lvl><id>2</id><sn><no>' . implode('</no> </sn> <sn> <no>', $params['gcodes']) . '</no></sn></lvl>';
        }
        if (isset($params['pcodes'])) {
            $data .= '<lvl><id>3</id><sn><no>' . implode('</no> </sn> <sn> <no>', $params['pcodes']) . '</no></sn></lvl>';
        }
        $sns = $request->addChild('sns', base64_encode(gzcompress($data)));
        $sns->addAttribute('compression', 'DEFLATE');
        $sns->addAttribute('uncompressed-length', strlen($data));
        
        return $xml;
    }
    
    static function addHeader(&$xml)
    {
        $header = $xml->addChild('header');
        $header->addChild('agent', self::$agentName);
        $header->addChild('timestamp', str_replace(' ', 'T', \Yii::$app->formatter->asDatetime(time(), 'yyyy-MM-dd HH:mm:ssZZZZZ')));
    }
    
    
    static function parseSns($data)
    {
        $s = '<data>' . $data . '</data>';
        
        $data = [];
        try {
            $xml = new \SimpleXMLElement($s);
            //примитивно без рекурсии на 3 уровня макс..
            
            if (isset($xml->lvl)) {
                foreach ($xml->lvl as $lvl) {
                    $parent = 'none';
                    if ($lvl->id == 2 || $lvl->id == 3) {
                        $level = (integer)$lvl->id;
                        if (isset($lvl->sn)) {
                            foreach ($lvl->sn as $sn) {
                                $code = (string)$sn->no;
                                $data[$level][$code] = $parent;
                                $sns = $sn->sns;
                                $parent2 = $code;
                                if (isset($sns->lvl)) {
                                    foreach ($sns->lvl as $lvl2) {
                                        $level2 = (integer)$lvl2->id;
                                        if (isset($lvl2->sn)) {
                                            foreach ($lvl2->sn as $sn2) {
                                                $code2 = (string)$sn2->no;
                                                $data[$level2][$code2] = $parent2;
                                                $sns2 = $sn2->sns;
                                                $parent3 = $code2;
                                                if (isset($sns2->lvl)) {
                                                    foreach ($sns2->lvl as $lvl3) {
                                                        $level3 = (integer)$lvl3->id;
                                                        if (isset($lvl3->sn)) {
                                                            foreach ($lvl3->sn as $sn3) {
                                                                $code3 = (string)$sn3->no;
                                                                $data[$level3][$code3] = $parent3;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            $data = [];
        }
        
        return $data;
    }
    
    //обработка полученных данных  массив[уровень 3,2,0 = п,г,и]["код"]= родитель | none
    //0 уровень не смотрим - просто сериализации у нас нет...
    static function getChilds($data, $c)
    {
        $ch = [];
        if (is_array($data)) {
            foreach ($data as $code => $parent) {
                if ($parent == $c) {
                    $ch[] = $code;
                }
            }
        }
        
        return $ch;
    }
    
    static function makeGofraNew($ocs, $prev)
    {
        $o = Ocs::findOne($ocs['id']);
        self::log('УПАКОВКА');
        $res = \Yii::$app->db->createCommand("
            select prev.parent,array_agg(prev.child) as child,cur.parent as curparent,array_agg(cur.child) as childs from (select * from ocs_data_pairs where ocs_data_id=:previd and type=0) as prev
                    FULL JOIN (select * from ocs_data_pairs WHERE ocs_data_id=:curid and type=0) as cur ON (prev.parent=cur.parent and prev.child=cur.child)
                    WHERE coalesce(prev.parent,'')!=coalesce(cur.parent,'')
                    group by 1,3
                    ORDER by cur.parent nulls first
        ", [
            ':previd' => $prev['id'],
            ':curid'  => $ocs['id'],
        ])->queryAll();
        foreach ($res as $data) {
            $code = $data['curparent'];
            if (empty($code) || $code == 'none') {
//                if(empty($code))
                $code = $data['parent'];
                if ($code != 'none') {
                    $child = pghelper::pgarr2arr($data['child']);
                    //пара распалась - нуно сделать изъятие
                    
                    $gcode = \Yii::$app->db->createCommand("SELECT * FROM _get_codes('code=''" . $code . "''')")->queryOne();
                    if (!empty($gcode)) {
                        $gchilds = pghelper::pgarr2arr($gcode['childrens']);
                        if (count(array_diff($child, $gchilds)) == 0 && count(array_diff($gchilds, $child)) == 0) {
                            self::log('Разгрупп: ' . $code . ' / ' . implode(',', $child));
                            //полное совпадение - надо разгрупп
                            $res = \Yii::$app->db->createCommand("SELECT make_ungrp(:userid,:code,'OCS') as res", [
                                ':userid' => $ocs['equip_uid'],
                                ':code'   => $code,
                            ])->queryOne();
                            $res = pghelper::pgarr2arr($res["res"]);
                            if ($res[0] != 0) {
                                self::tqs_notify($o->object->name, 'ошибка разгруппировки: ' . $res[1]);
                            }
                        } else {
                            self::log('Изъятие: ' . $code . ' / ' . implode(',', $child));
                            //совпадение не полное - надо изъятие
                            $res = \Yii::$app->db->createCommand("SELECT make_grp_exclude2(:userid,:childs,'other','OCS') as res", [
                                ':userid' => $ocs['equip_uid'],
                                ':childs' => pghelper::arr2pgarr($child),
                            ])->queryOne();
                            $res = \app\modules\itrack\components\pghelper::pgarr2arr($res['res']);
                            if ($res[0] != 0) {
                                self::tqs_notify($o->object->name, 'ошибка изъятия: ' . $res[1]);
                            }
                        }
                    } else {
                        //не найден групповой код
                        self::log('групповой код не найден ' . $code);
                    }
                }
            } else {
                $childs = pghelper::pgarr2arr($data['childs']);
                self::log('вход: ' . $code . ' / ' . implode(',', $childs));
                
                if (count($childs)) {
                    //выборка и проверка кодов
                    $gcode = \Yii::$app->db->createCommand("SELECT * FROM _get_codes('code=''" . $code . "''')")->queryOne();
                    if (!empty($gcode)) {
                        $codes = \Yii::$app->db->createCommand('select *,is_removed(flag) as removed,is_defected(flag) as defected from _get_codes_array(:codes)', [':codes' => pghelper::arr2pgarr($childs)])->queryAll();
                        if (count($codes) != count($childs)) {
                            //не найдены какие то чилды
                            self::log('не все чилды найдены: ' . print_r($codes, true) . print_r($childs, true));
                        } else {
                            $err = false;
                            $toexclude = $topack = $toadd = [];
                            foreach ($codes as $c) {
                                if ($c['removed'] || $c['defected']) {
                                    continue;
                                }
                                if ($c['flag'] > 0) {
                                    if (!empty($c['parent_code']) && $c['parent_code'] != $code) {
                                        $toexclude[$c['code']] = $c;
                                    }
                                    $toadd[$c['code']] = $c;
                                } else {
                                    $topack[$c['code']] = $c;
                                }
                                if (!in_array($c['generation_uid'], $ocs['generations'])) {
                                    $err = true;
                                }
                            }
                            if ($err) {
                                //чужие коды!!!
                                self::log('коды из другой генерации');
                            } else {
                                if ($gcode['flag'] == 0) {
                                    //гофракод новый
                                    if (count($topack)) {
                                        //индивидуальные на упаковку
                                        self::log('упаковка: (' . $code . '-' . implode(',', array_keys($topack)) . ')');
                                        
                                        $res = \Yii::$app->db->createCommand('SELECT make_pack(:userid,:gcode,:childs,false,false) as res', [
                                            ':userid' => $ocs['equip_uid'],
                                            ':gcode'  => $code,
                                            ':childs' => pghelper::arr2pgarr(array_keys($topack)),
                                        ])->queryOne();
                                        $res = pghelper::pgarr2arr($res['res']);
                                        if ($res[0] != 0) {
                                            //ошибка упаковки
                                            self::tqs_notify($o->object->name, 'ошибка упаковки: ' . $res[1]);
                                            if(preg_match('#данный момент над кодом уже выполняется операция, дождитесь ее завершения#si', $res[1]))
                                                \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :curid and type = 0 and parent=:parent', [
                                                    ':curid'  => $ocs['id'],
                                                    ':parent' => $code,
                                                ])->execute();
                                        }
                                        //все ОК!!!
                                    }
                                    if (count($toadd)) {
                                        if (count($toexclude)) {
                                            self::log('изымаем: (' . implode(',', array_keys($toexclude)) . ')');
                                            $res = \Yii::$app->db->createCommand("SELECT make_grp_exclude2(:userid,:childs,'other','OCS') as res", [
                                                ':userid' => $ocs['equip_uid'],
                                                ':childs' => pghelper::arr2pgarr(array_keys($toexclude)),
                                            ])->queryOne();
                                            $res = pghelper::pgarr2arr($res['res']);
                                            if ($res[0] != 0) {
                                                //ошибка упаковки
                                                self::tqs_notify($o->object->name, 'ошибка изъятия: ' . $res[1]);
                                            }
                                        }
                                        //индивидуальные на добавление
                                        self::log('добаление: (' . $code . '-' . implode(',', array_keys($toadd)) . ')');
                                        
                                        $res = \Yii::$app->db->createCommand('SELECT make_gofra_add_uni(:userid,:gcode,:childs,false) as res', [
                                            ':userid' => $ocs['equip_uid'],
                                            ':gcode'  => $code,
                                            ':childs' => pghelper::arr2pgarr(array_keys($toadd)),
                                        ])->queryOne();
                                        $res = pghelper::pgarr2arr($res['res']);
                                        if ($res[0] != 0) {
                                            //ошибка упаковки
                                            self::tqs_notify($o->object->name, 'ошибка добавления: ' . $res[1]);
                                            if (preg_match('#данный момент над кодом уже выполняется операция, дождитесь ее завершения#si', $res[1]))
                                                \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :curid and type = 0 and parent=:parent', [
                                                    ':curid'  => $ocs['id'],
                                                    ':parent' => $code,
                                                ])->execute();
                                        }
                                    }
                                } else {
                                    //гофракод старый
                                    if (count($toexclude)) {
                                        self::log('изымаем: (' . implode(',', array_keys($toexclude)) . ')');
                                        $res = \Yii::$app->db->createCommand("SELECT make_grp_exclude2(:userid,:childs,'other','OCS') as res", [
                                            ':userid' => $ocs['equip_uid'],
                                            ':childs' => pghelper::arr2pgarr(array_keys($toexclude)),
                                        ])->queryOne();
                                        $res = pghelper::pgarr2arr($res['res']);
                                        if ($res[0] != 0) {
                                            //ошибка упаковки
                                            self::tqs_notify($o->object->name, 'ошибка изъятия: ' . $res[1]);
                                        }
                                    }
                                    $toadd = array_merge($toadd, $topack);
                                    if (count($toadd)) {
                                        //индивидуальные на добавление
                                        self::log('добаление: (' . $code . '-' . implode(',', array_keys($toadd)) . ')');
                                        
                                        $res = \Yii::$app->db->createCommand('SELECT make_gofra_add_uni(:userid,:gcode,:childs,false) as res', [
                                            ':userid' => $ocs['equip_uid'],
                                            ':gcode'  => $code,
                                            ':childs' => pghelper::arr2pgarr(array_keys($toadd)),
                                        ])->queryOne();
                                        $res = pghelper::pgarr2arr($res['res']);
                                        if ($res[0] != 0) {
                                            //ошибка упаковки
                                            self::tqs_notify($o->object->name, 'ошибка добавления: ' . $res[1]);
                                            if (preg_match('#данный момент над кодом уже выполняется операция, дождитесь ее завершения#si', $res[1]))
                                                \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :curid and type = 0 and parent=:parent', [
                                                    ':curid'  => $ocs['id'],
                                                    ':parent' => $code,
                                                ])->execute();
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        //не найден групповой код
                        self::log('групповой код не найден ' . $code);
                    }
                }
            }
        }
    }
    
    static function makePalletaNew($ocs, $prev)
    {
        $o = Ocs::findOne($ocs['id']);
        self::log('ПАЛЕТИРОВАНИЕ');
        $res = \Yii::$app->db->createCommand("
            select prev.parent,array_agg(prev.child) as child,cur.parent as curparent,array_agg(cur.child) as childs from (select * from ocs_data_pairs where ocs_data_id=:previd and type=2) as prev
                    FULL JOIN (select * from ocs_data_pairs WHERE ocs_data_id=:curid and type=2) as cur ON (prev.parent=cur.parent and prev.child=cur.child)
                    WHERE coalesce(prev.parent,'')!=coalesce(cur.parent,'')
                    group by 1,3
                    ORDER by cur.parent nulls first
        ", [
            ':previd' => $prev['id'],
            ':curid'  => $ocs['id'],
        ])->queryAll();
        foreach ($res as $data) {
            $code = $data['curparent'];
            if (empty($code) || $code == 'none') {
                $code = $data['parent'];
                if ($code != 'none') {
                    $child = pghelper::pgarr2arr($data['child']);
                    //пара распалась - нуно сделать изъятие
                    
                    $gcode = \Yii::$app->db->createCommand("SELECT * FROM _get_codes('code=''" . $code . "''')")->queryOne();
                    if (!empty($gcode)) {
                        $gchilds = pghelper::pgarr2arr($gcode['childrens']);
                        if (count(array_diff($child, $gchilds)) == 0 && count(array_diff($gchilds, $child)) == 0) {
                            self::log('Разгрупп: ' . $code . '/ ' . implode(',', $child));
                            //полное совпадение - надо разгрупп
                            $res = \Yii::$app->db->createCommand("SELECT make_ungrp(:userid,:code,'OCS') as res", [
                                ':userid' => $ocs['equip_uid'],
                                ':code'   => $code,
                            ])->queryOne();
                            $res = pghelper::pgarr2arr($res['res']);
                            if ($res[0] != 0) {
                                self::tqs_notify($o->object->name, 'ошибка разгруппировки: ' . $res[1]);
                            }
                        } else {
                            self::log('Изъятие: ' . $code . '/ ' . implode(',', $child));
                            //совпадение не полное - надо изъятие
                            $res = \Yii::$app->db->createCommand("SELECT make_grp_exclude2(:userid,:childs,'other','OCS') as res", [
                                ':userid' => $ocs['equip_uid'],
                                ':childs' => pghelper::arr2pgarr($child),
                            ])->queryOne();
                            $res = pghelper::pgarr2arr($res['res']);
                            if ($res[0] != 0) {
                                self::tqs_notify($o->object->name, 'ошибка изъятия: ' . $res[1]);
                            }
                        }
                    } else {
                        //не найден групповой код
                        self::log('групповой код не найден ' . $code);
                    }
                }
            } else {
                $childs = pghelper::pgarr2arr($data['childs']);
                self::log('вход: ' . $code . ' / ' . implode(',', $childs));
                if (count($childs)) {
                    //выборка и проверка кодов
                    
                    $gcode = \Yii::$app->db->createCommand("SELECT * FROM _get_codes('code=''" . $code . "''')")->queryOne();
                    if (!empty($gcode)) {
                        $codes = \Yii::$app->db->createCommand('select *,is_removed(flag) as removed,is_defected(flag) as defected from _get_codes_array(:codes)', [':codes' => pghelper::arr2pgarr($childs)])->queryAll();
                        if (count($codes) != count($childs)) {
                            //не найдены какие то чилды
                            self::log('не все чилды найдены: ' . print_r($codes, true) . print_r($childs, true));
                        } else {
                            $err = false;
                            $topack = $toadd = $toexclude = [];
                            foreach ($codes as $c) {
                                if ($c['removed'] || $c['defected']) {
                                    continue;
                                }
                                if ($gcode['flag'] == 0) {
                                    //паллета не собрана
                                    if (empty($c['parent_code'])) {
                                        $topack[$c['code']] = $c;
                                    } else {
                                        if ($c['parent_code'] != $code) {
                                            $toexclude[$c['code']] = $c;
                                        }
                                        $toadd[$c['code']] = $c;
                                    }
                                } else {
                                    if (empty($c['parent_code'])) {
                                        $toadd[$c['code']] = $c;
                                    } else {
                                        if ($c['parent_code'] != $code) {
                                            $toexclude[$c['code']] = $c;
                                            $toadd[$c['code']] = $c;
                                        }
                                    }
                                }
                                if ($c['flag'] == 0) {
                                    $err = true;
                                }
                            }
                            
                            if ($err) {
                                //бага гофра то не активная!!
                                self::log('групповой код не активен ' . $code);
                            }
                            
                            if (count($topack)) {
                                self::log('упаковка: (' . $code . '-' . implode(',', array_keys($topack)) . ')');
                                $res = \Yii::$app->db->createCommand('SELECT make_paleta_uni(:userid,:gcode,:childs, false) as res', [
                                    ':userid' => $ocs['equip_uid'],
                                    ':gcode'  => $code,
                                    ':childs' => pghelper::arr2pgarr(array_keys($topack)),
                                ])->queryOne();
                                $res = pghelper::pgarr2arr($res['res']);
                                if ($res[0] != 0) {
                                    //ошибка упаковки
                                    self::tqs_notify($o->object->name, 'ошибка палетировнаия: ' . $res[1]);
                                    if (preg_match('#данный момент над кодом уже выполняется операция, дождитесь ее завершения#si', $res[1]))
                                        \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :curid and type = 2 and parent=:parent', [
                                            ':curid'  => $ocs['id'],
                                            ':parent' => $code,
                                        ])->execute();
                                }
                            }
                            if (count($toexclude)) {
                                self::log('изымаем: (' . implode(',', array_keys($toexclude)) . ')');
                                $res = \Yii::$app->db->createCommand("SELECT make_grp_exclude2(:userid,:childs,'other','OCS') as res", [
                                    ':userid' => $ocs['equip_uid'],
                                    ':childs' => pghelper::arr2pgarr(array_keys($toexclude)),
                                ])->queryOne();
                                $res = pghelper::pgarr2arr($res['res']);
                                if ($res[0] != 0) {
                                    //ошибка упаковки
                                    self::tqs_notify($o->object->name, 'ошибка изъятия: ' . $res[1]);
                                }
                            }
                            if (count($toadd)) {
                                self::log('добаление: (' . $code . '-' . implode(',', array_keys($toadd)) . ')');
                                
                                $res = \Yii::$app->db->createCommand('SELECT make_paleta_add_uni(:userid,:gcode,:childs, false) as res', [
                                    ':userid' => $ocs['equip_uid'],
                                    ':gcode'  => $code,
                                    ':childs' => pghelper::arr2pgarr(array_keys($toadd)),
                                ])->queryOne();
                                $res = pghelper::pgarr2arr($res['res']);
                                if ($res[0] != 0) {
                                    //ошибка упаковки
                                    self::tqs_notify($o->object->name, 'ошибка добавления: ' . $res[1]);
                                    if (preg_match('#данный момент над кодом уже выполняется операция, дождитесь ее завершения#si', $res[1]))
                                        \Yii::$app->db->createCommand('DELETE FROM ocs_data_pairs WHERE ocs_data_id = :curid and type = 2 and parent=:parent', [
                                            ':curid'  => $ocs['id'],
                                            ':parent' => $code,
                                        ])->execute();
                                }
                            }
                        }
                    } else {
                        //не найден групповой код
                        self::log('групповой код не найден ' . $code);
                    }
                }
            }
        }
    }
    
    static function tqs_notify($obj, $message)
    {
        try {
            self::log($message);
            $notify = new \app\modules\itrack\models\Notify();
            $notify->cansend = true;
            $servName = \Yii::$app->params["monitoring"]["notifyName"] ?? 'Сервер не определен';
            if (isset(\Yii::$app->params["monitoring"]["notify"])) {
                $notify->send(\Yii::$app->params["monitoring"]["notify"], $servName . ' - OCS .', "Объект: $obj\n $message");
            }
        } catch (\Exception $ex) {
        }
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
