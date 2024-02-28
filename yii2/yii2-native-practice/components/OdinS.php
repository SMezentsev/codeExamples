<?php

namespace app\modules\itrack\components;

use app\modules\itrack\models\Fns;

class OdinS
{
    
    
    static function parse(Fns $doc)
    {

        $docBody = file_get_contents($doc->getFileName(true));
        $xml = @new \SimpleXMLElement($docBody);
        $action_id = (string)$xml->attributes()["action_id"];
        
        if ($action_id == '0300')   //заказ кодов
        {
            self::parseNewCodes($xml, $doc);
        }
        if ($action_id == '0200')   //результат проверки исходящего дока
        {
            self::parseResult($xml, $doc);
        }
        if ($action_id == '0804')   //док для отгрузки/перемещения
        {
            self::parseOutcome($xml, $doc);
        }
        if ($action_id == '0700')   //док для выпуска готовой продукции 313/1
        {
            self::parseEmission($xml, $doc);
        }
        if ($action_id == '0800')   //док для выпуска готовой продукции 313/1
        {
            self::parseRetail($xml, $doc);
        }
    }
    
    static function canEndPacking($fnsid)
    {
        //если есть 1с - то эти операции должны ждать подтверждения с 1С
        if (isset(\Yii::$app->params["1c"])
            && isset(\Yii::$app->params["1c"]["enable"])
            && \Yii::$app->params["1c"]["enable"] === true
            && in_array($fnsid, ["431", "381", "415", "441"])
        ) {
            return true;
        }
        
        return false;
    }
    
    static function parseRetail($xml, Fns $doc)
    {
        $params = [];
        if (isset($xml->action_id)) {
            $params["action_id"] = (string)$xml->attributes()["action_id"];
        }
        if (isset($xml->doc_id)) {
            $params["doc_id"] = (string)$xml->doc_id;
        }
        if (isset($xml->Doc_number)) {
            $params["Doc_number"] = (string)$xml->Doc_number;
        }
        if (isset($xml->doc_date_1c)) {
            $params["doc_date_1c"] = (string)$xml->doc_date_1c;
        }
        if (isset($xml->Is_copy)) {
            $params["Is_copy"] = (string)$xml->Is_copy;
        }
        if (isset($xml->subject_id)) {
            $params["subject_id"] = (string)$xml->subject_id;
        }
        if (isset($xml->subject_id_name)) {
            $params["subject_id_name"] = (string)$xml->subject_id_name;
        }
        if (isset($xml->receiver_id)) {
            $params["receiver_id"] = (string)$xml->receiver_id;
        }
        if (isset($xml->receiver_id_name)) {
            $params["receiver_id_name"] = (string)$xml->receiver_id_name;
        }
        if (isset($xml->doc_num)) {
            $params["doc_num"] = (string)$xml->doc_num;
        }
        if (isset($xml->doc_date)) {
            $params["doc_date"] = (string)$xml->doc_date;
        }
        if (isset($xml->contract_type)) {
            $params["contract_type"] = (string)$xml->contract_type;
        }
        if (isset($xml->contract_name)) {
            $params["contract_name"] = (string)$xml->contract_name;
        }
        if (isset($xml->source_id)) {
            $params["source_id"] = (string)$xml->source_id;
        }
        if (isset($xml->source_name)) {
            $params["source_name"] = (string)$xml->source_name;
        }
        if (isset($xml->receiver_inn)) {
            $params["inn"] = (string)$xml->receiver_inn;
        }
        if (isset($xml->receiver_kpp)) {
            $params["kpp"] = (string)$xml->receiver_kpp;
        }
        if (isset($xml->ul)) {
            $params["ul"] = (string)$xml->ul;
        }
        if (isset($xml->lp)) {
            foreach ($xml->lp->product as $el) {
                $params["gtins2"][] = [(string)$el->gtin, (string)$el->series_number, (string)$el->prod_qnt, (string)$el->cost, (string)$el->vat_value];
                $params["gtins"][(string)$el->gtin] = [(string)$el->cost, (string)$el->vat_value];
            }
        }
        $doc->docid = $params["doc_id"];
        $doc->note = $params["doc_num"] . "|" . $params["doc_date"];
        $doc->data = pghelper::arr2pgarr([$params["action_id"], $params["doc_num"], $params["doc_date"]]);
        $doc->fns_params = serialize($params);
        $doc->save(false);
    }
    
    static function parseEmission($xml, Fns $doc)
    {
        $errors = [];
        $params = [
            'doc_id'           => (integer)$xml->doc_id,
            'doc_date'         => (string)$xml->doc_date,
            'doc_id_src'       => (integer)$xml->doc_id_src,    //ид дока по которому пришел ответ 0500 или 0600 - по нему искать 313/1
            'doc_date_src'     => (integer)$xml->doc_date_src,
            'confirm_doc_type' => (integer)$xml->confirm_doc_type,
            'confirm_doc_num'  => (integer)$xml->confirm_doc_num,
            'confirm_doc_date' => (integer)$xml->confirm_doc_date,
        ];
        $fns = Fns::find()->andWhere(['id' => $params["doc_id_src"]])->one();
        if (!empty($fns)) {
            //нашли отбор - по отбору ищем 313/1
            $fns = Fns::find()->andWhere([
                "product_uid"   => $fns->product_uid,
                "operation_uid" => Fns::OPERATION_EMISSION_ID,
            ])->andFilterWhere(['<=', 'state', Fns::STATE_READY])
                ->andFilterWhere(['<=', 'created_at', $fns->created_at])->one();
            if (!empty($fns)) {
                $fparams = $fns->params;
                $fparams["doc_num"] = (string)$params["confirm_doc_num"];
                $fparams["doc_date"] = (string)$params["confirm_doc_date"];
                $fparams["confirm_doc"] = (string)$params["confirm_doc_type"];
                $fns->fns_params = serialize($fparams);
                $fns->state = Fns::STATE_READY;
                $fns->save(false);
            }
        }
    }
    
    static function parseOutcome($xml, Fns $doc)
    {
        $errors = [];
        $params = [
            'doc_id'    => (integer)$xml->doc_id,
            'doc_date'  => (string)$xml->doc_date,
            'prim_id'   => (integer)$xml->prim_id,    //ид дока по которому пришел ответ
            'prim_date' => (integer)$xml->prim_date,
            'scn_id'    => (integer)$xml->scn_id,           //doc_num   для фнс
            'scn_date'  => (integer)$xml->scn_date,         //doc_date     для фнс
        ];
        $fns = Fns::find()->andWhere(['id' => $params["prim_id"]])->one();
        if (!empty($fns)) {
            $fparams = $fns->params;
            $fparams["doc_num"] = (string)$params["scn_id"];
            $fparams["doc_date"] = (string)$params["scn_id"];
            $fns->fns_params = serialize($fparams);
            $fns->state = Fns::STATE_CREATED;
            $fns->save(false);
        }
    }
    
    static function parseResult($xml, Fns $doc)
    {
        $errors = [];
        $params = [
            'doc_id'     => (integer)$xml->doc_id,
            'doc_date'   => (string)$xml->doc_date,
            'msg_id'     => (integer)$xml->msg_id,
            'msg_date'   => (string)$xml->msg_date,
            'resultCode' => (integer)$xml->resultCode,
            'errors'     => (string)$xml->errors,
        ];
        $fns = Fns::find()->andWhere(['id' => $params["msg_id"]])->one();
        if (!empty($fns)) {
            if (!$params["resultCode"]) {
                //принят
                $fns->note = "Принят 1С";
            } else {
                // не принят
                $fns->note = "Не принят 1С, ошибка: " . $params["errors"];
            }
            $fns->save(false);
        }
    }
    
    static function parseNewCodes($xml, Fns $doc)
    {
        $errors = [];
        $params = [
            'doc_id'           => (string)$xml->doc_id,
            'doc_date'         => str_replace('T', '', (string)$xml->doc_date),
            'Order_Num'        => (string)$xml->OrderNum,
            'prod_date'        => str_replace('T', '', (string)$xml->prod_date),
            'manufact_id'      => (string)$xml->manufact_id,
            'manufact_name'    => (string)$xml->manufact_name,
            'order_type'       => (integer)$xml->order_type,
            'owner_id'         => (string)$xml->owner_id,
            'owner_name'       => (string)$xml->owner_name,
            'gtin'             => (string)$xml->gtin,
            'nomenclatureCode' => (string)$xml->nomenclatureCode,
            'nomenclatureName' => (string)$xml->nomenclatureName,
            'series'           => (string)$xml->series,
            'expirationDate'   => str_replace('T', '', (string)$xml->expirationDate),
            'tnved_code'       => (string)$xml->tnved_code,
            'Qnt'              => (integer)$xml->Qnt,
            'Qnt_in_box'       => (integer)$xml->Qnt_in_box,
            'UnitNumberID'     => (integer)$xml->UnitNumberID,
            'object'           => (string)$xml->object,
        ];
        $trans = \Yii::$app->db->beginTransaction();
        
        if (empty($params["doc_id"])) {
            $errors[] = "Поле doc_id - пустое";
        }
        if (empty($params["doc_date"])) {
            $errors[] = "Поле doc_date - пустое";
        }
        if (empty($params["Order_Num"])) {
            $errors[] = "Поле Order_Num - пустое";
        }
        if (empty($params["object"])) {
            $errors[] = "Поле object - пустое";
        }
        
        $rootUser = \app\modules\itrack\models\User::findByLogin('root');
        if (empty($rootUser)) {
            $errors[] = "Не удается найти пользователя root";
        }
        \Yii::$app->user->setIdentity($rootUser);
        
        $object = \app\modules\itrack\models\Facility::find()->andWhere(['code1c' => $params['object'], 'external' => false])->one();
        if (empty($object)) {
            $errors[] = "Не удается найти объект (" . $params["object"] . ")";
        }
        try {
            $equips = \app\modules\itrack\models\Equip::find()->andWhere([
                'object_uid' => $object->id,
                'active'     => true,
            ])->all();
        } catch (\Exception $ex) {
        }
        $equip = null;
        foreach ($equips as $equip) {
            if ($equip->type == \app\modules\itrack\models\Equip::TYPE_OCS) {
                break;
            }
        }
        if (empty($equip)) {
            $errors[] = "Не удается найти оборудование OCS на заданном объекте";
        }
        
        $manufacturer = \app\modules\itrack\models\Manufacturer::find()->andWhere(['fnsid' => $params['manufact_id']])->one();
        if (empty($manufacturer)) {
            $manufacturer = new \app\modules\itrack\models\Manufacturer();
            $manufacturer->scenario = 'odinStask';
            $manufacturer->load(['name' => $params['manufact_name'], 'fnsid' => $params['manufact_id']], '');
            if (!$manufacturer->save()) {
                $errors[] = 'Ошибка создания производителя (' . implode(", ", array_map(function ($v) {
                        return implode(' - ', $v);
                    }, $manufacturer->errors)) . ')';
            } else {
                $manufacturer->refresh();
            }
        }
        
        try {
            $nomenclature = \app\modules\itrack\models\Nomenclature::find()->andWhere(['code1c' => $params['nomenclatureCode']])->one();
            if (empty($nomenclature)) {
                $nomenclature = new \app\modules\itrack\models\Nomenclature();
                $nomenclature->scenario = 'odinStask';
                $nomenclature->load([
                    'ean13'            => substr($params["gtin"], 1, 13),
                    'created_by'       => $rootUser->id,
                    'name'             => $params["nomenclatureName"],
                    'cnt'              => $params["Qnt_in_box"],
                    'gtin'             => $params["gtin"],
                    'manufacturer_uid' => $manufacturer->id,
                    'code1c'           => $params['nomenclatureCode'],
                    'tnved'            => $params["tnved_code"],
                    'fns_order_type'   => $params["order_type"],
                    'fns_owner_id'     => $params["owner_id"],
                ], '');
                if (!$nomenclature->save()) {
                    $errors[] = 'Ошибка создания номенклатуры (' . implode(", ", array_map(function ($v) {
                            return implode(' - ', $v);
                        }, $nomenclature->errors)) . ')';
                } else {
                    $nomenclature->refresh();
                }
            }
        } catch (\Exception $ex) {
            $errors[] = "Ошибка создания номенклатуры";
        }
        
        try {
            $product = \app\modules\itrack\models\Product::find()->andWhere([
                'nomenclature_uid' => $nomenclature->id,
                'series'           => $params["series"],
                //'cdate' => \Yii::$app->formatter->asDate($params["prod_date"], "php:d m Y"),
                //'expdate' => \Yii::$app->formatter->asDate($params["expirationDate"], "php:d m Y"),
                'object_uid'       => $object->id,
            ])->one();
            if (empty($product)) {
                $product = new \app\modules\itrack\models\Product();
                $product->scenario = 'odinStask';
                $product->load([
                    'created_by'       => $rootUser->id,
                    'nomenclature_uid' => $nomenclature->id,
                    'series'           => $params["series"],
                    'cdate'            => \Yii::$app->formatter->asDate($params["prod_date"], "php:d m Y"),
                    'expdate'          => \Yii::$app->formatter->asDate($params["expirationDate"], "php:d m Y"),
                    'expdate_full'     => \Yii::$app->formatter->asDate($params["expirationDate"], "php:d m Y"),
                    'object_uid'       => $object->id,
                ], '');
                if (!$product->save()) {
                    $errors[] = 'Ошибка создания товарной карты (' . implode(", ", array_map(function ($v) {
                            return implode(' - ', $v);
                        }, $product->errors)) . ')';
                } else {
                    $product->refresh();
                }
            }
        } catch (\Exception $exc) {
            $errors[] = "Ошибка создания товарной карты";
        }
        
        
        try {
            $ocs = \app\modules\itrack\models\Ocs::find()->andWhere(new \yii\db\Expression("info[1] = '" . $params["Order_Num"] . "'"))->one();
            if (!empty($ocs)) {
                $errors[] = 'Заказ с номером (' . $params["Order_Num"] . ') - уже был обработан ранее';
            } else {
                $values = [
                    "equip_uid"   => $equip->id,
                    "info"        => pghelper::arr2pgarr(['Order_Num' => $params["Order_Num"]]),
                    'object_uid'  => $object->id,
                    'created_by'  => $rootUser->id,
                    'product_uid' => $product->id,
                    'state'       => \app\modules\itrack\models\Ocs::STATE_CREATED,
                    'cnt'         => ceil($params["Qnt"] * 1.1),
                ];
                $ocs = new \app\modules\itrack\models\Ocs();
                $ocs->load($values, '');
                if (!$ocs->save()) {
                    $errors[] = 'Ошибка создания заказа';
                }
                $ocs->refresh();
                \app\modules\itrack\models\Ocs::createGenerations($ocs->id, ceil($params["Qnt"] * 1.1), 0, 0);
            }
        } catch (\Exception $ex) {
            $errors[] = 'Ошибка создания заказа';
        }
        
        if (!empty($errors)) {
            $trans->rollback();
            $report = new Fns();
            $report->load([
                'operation_uid' => Fns::OPERATION_1C_OUT,
                'state'         => Fns::STATE_1CPREPARING,
                'created_by'    => 0,
                'data'          => pghelper::arr2pgarr([
                    '0200',
                    $params['doc_id'],
                    $params['doc_date'],
                    $params['Order_Num'],
                    '1',
                    serialize($errors),
                ]),
            ], '');
            $report->save(false);
        } else {
            $trans->commit();
            $report = new Fns();
            $report->load([
                'operation_uid' => Fns::OPERATION_1C_OUT,
                'state'         => Fns::STATE_1CPREPARING,
                'created_by'    => 0,
                'data'          => pghelper::arr2pgarr([
                    '0200',
                    $params['doc_id'],
                    $params['doc_date'],
                    $params['Order_Num'],
                    '0',
                    serialize($errors),
                ]),
            ], '');
            $report->save(false);
        }
        
        $doc->fns_params = serialize($params);
        $doc->note = implode("\n", $errors);
        $doc->save(false);
    }
    
    
    static function createOtbor(Fns $doc, $action)
    {
        $xml = new \SimpleXMLElement("<wh_control_samples_act/>");
        $xml->addAttribute('action_id', $action);
        $xml->addChild("doc_id", $doc->id);
        $xml->addChild("doc_date", $doc->cdt);
        $xml->addChild("person_control_fio", $doc->user->fio);
        $data = \app\modules\itrack\components\pghelper::pgarr2arr($doc->data);
        list($xmltype, $cnt1, $cnt2, $cnt3, $series, $gtin, $num) = $data;
        $xml->addChild("gtin", $gtin);
        $xml->addChild("series", $series);
        $xml->addChild("Order_Num", $num);
        $q1 = $xml->addChild("qnt_control_samples_type1", $cnt1);
        $q2 = $xml->addChild("qnt_control_samples_type2", $cnt2);
        $q3 = $xml->addChild("qnt_control_samples_type3", $cnt3);
//        $q1->addAttribute("type", "На контроль");
//        $q2->addAttribute("type", "В архив ОКК");
//        $q3->addAttribute("type", "Декларирование/Сертификация");
        return $xml->asXML();
    }
    
    static function createReport(Fns $doc, $action)
    {
        $xml = new \SimpleXMLElement('<report/>');
        $xml->addAttribute('action_id', $action);
        $xml->addChild("doc_id", $doc->id);
        $xml->addChild("doc_date", $doc->cdt);
        $data = \app\modules\itrack\components\pghelper::pgarr2arr($doc->data);
        list($xmltype, $docId, $docDate, $odinSnum, $resultCode, $errors) = $data;
        $xml->addChild("qr_id", $odinSnum);
        $xml->addChild("qr_date", $docDate);
        $xml->addChild("qr_id_scan", $docId);
        $xml->addChild("resultCode", $resultCode);
        if ($resultCode) {
            $q1 = $xml->addChild("errors");
            $arr = unserialize($errors);
            foreach ($arr as $err) {
                $q1->addChild('error', $err);
            }
        }
        
        return $xml->asXML();
    }
    
    static function createReport0200(Fns $doc, $action)
    {
        $xml = new \SimpleXMLElement('<report/>');
        $xml->addAttribute('action_id', $action);
        $xml->addChild("doc_id", $doc->id);
        $xml->addChild("doc_date", $doc->cdt);
        $data = \app\modules\itrack\components\pghelper::pgarr2arr($doc->data);
        list($xmltype, $docId, $docDate, $odinSnum, $resultCode, $errors) = $data;
        $xml->addChild("msg_id", $docId);
        $xml->addChild("msg_date", $docDate);
        $xml->addChild("resultCode", $resultCode);
        if ($resultCode) {
            $q1 = $xml->addChild("errors");
            $arr = unserialize($errors);
            foreach ($arr as $err) {
                $q1->addChild('error', $err);
            }
        }
        
        return $xml->asXML();
    }
    
    static function createEndOfSeries(Fns $doc, $action, $report)
    {
        $xml = new \SimpleXMLElement('<report/>');
        
        $xml->addAttribute('action_id', $action);
        $xml->addChild("doc_id", $doc->id);
        $xml->addChild("doc_date", $doc->cdt);
        $data = \app\modules\itrack\components\pghelper::pgarr2arr($doc->data);
        list($xmltype, $series, $reportId) = $data;
        list($ser, $gtin, $indcnt, $gfrcnt, $pltcnt) = pghelper::pgarr2arr($report->params);
        $xml->addChild("series", $series);
        $xml->addChild("gtin", $gtin);
        $xml->addChild("sscc_case_qnt", $gfrcnt);
        $xml->addChild("sscc_pallet_qnt", $pltcnt);
        $xml->addChild("sgtin_qnt", $indcnt);

//отправлять файл отчета в папку 1с????
        
        return $xml->asXML();
    }
    
}
