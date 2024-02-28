<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 25.09.15
 * Time: 16:27
 */

namespace app\modules\itrack\controllers;


use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Conflicts;
use app\modules\itrack\models\Facility;

class MergeController extends \yii\rest\Controller
{
    use ControllerTrait;
    
    public function init()
    {
        \Yii::$app->user->identityClass = Facility::class;
        parent::init();
    }
    
    /**
     * Принятие файла изменений для сращивания
     */
    public function actionSklad()
    {
        // @todo Принять файл
        // @todo Поставить задачу на обработку
        $ids = [];
        
        //operations
        $request = json_decode(\Yii::$app->getRequest()->getBodyParam('operations'));
        if (is_array($request)) {
            foreach ($request as $obj) {
                $obj = get_object_vars($obj);
                \Yii::$app->db->createCommand("INSERT INTO operations (
                                                                          created_by,
                                                                          state,
                                                                          code,
                                                                          codes,
                                                                          product_uid,
                                                                          object_uid,
                                                                          newobject_uid,
                                                                          invoice_uid,
                                                                          operation_uid,
                                                                          created_at,
                                                                          created_time,
                                                                          data,
                                                                          fnsid,
                                                                          dbschema,
                                                                          indcnt,
                                                                          grpcnt,
                                                                          code_flag,
                                                                          docid,
                                                                          note
                                                                    )
                                                                    VALUES (:a1,:a2,:a3,:a4,:a5,:a6,:a7,:a8,:a9,:a10,:a11,:a12,:a13,:a14,:a15,:a16,:a17,:a18,:a19)
                                         RETURNING id"
                    , [
                        ':a1'  => $obj["created_by"],
                        ':a2'  => $obj["state"],
                        ':a3'  => $obj["code"],
                        ':a4'  => $obj["codes"],
                        ':a5'  => $obj["product_uid"],
                        ':a6'  => $obj["object_uid"],
                        ':a7'  => $obj["newobject_uid"],
                        ':a8'  => $obj["invoice_uid"],
                        ':a9'  => $obj["operation_uid"],
                        ':a10' => $obj["created_at"],
                        ':a11' => $obj["created_time"],
                        ':a12' => $obj["data"],
                        ':a13' => $obj["fnsid"],
                        ':a14' => $obj["dbschema"],
                        ':a15' => $obj["indcnt"],
                        ':a16' => $obj["grpcnt"],
                        ':a17' => $obj["code_flag"],
                        ':a18' => $obj["docid"],
                        ':a19' => $obj["note"],
                    ])->execute();
                
                $ids[] = $obj['id'];
            }
        }
        
        //коды
        $request = json_decode(\Yii::$app->getRequest()->getBodyParam('request_codes'));
        
        if (is_array($request)) {
            foreach ($request as $obj) {
                // @todo проверка, а могут ли приходить изменения кода с данного объекта
                //
                $obj = get_object_vars($obj);
//            if($obj["object_uid"] != \Yii::$app->user->getIdentity()->id)continue;
//file_put_contents("/srv/www/itrack-rf2-api.dev-og.com/runtime/sda","КОд: \n".print_r($obj,true),FILE_APPEND);
                $code = \Yii::$app->db->createCommand("SELECT * FROM codes WHERE id = " . intval($obj["id"]))->queryOne();
                if (!empty($code)) {
//file_put_contents("/srv/www/itrack-rf2-api.dev-og.com/runtime/sda", "КОд найден \n", FILE_APPEND);
                    if ($code["lmtime"] > $obj["lmtime"]) {
                        //код в нашей базе новее чем присланный с объекта - закидываем в журнал конфликтов
//file_put_contents("/srv/www/itrack-rf2-api.dev-og.com/runtime/sda", "конфликт\n", FILE_APPEND);
                        
                        $conflict = new Conflicts();
                        $conflict->load(
                            [
                                'typeof'     => 'codes',
                                'oldvalue'   => json_encode($code),
                                'newvalue'   => json_encode($obj),
                                'object_uid' => \Yii::$app->user->getIdentity()->id,
                            ]
                            , '');
                        $res = $conflict->save();
                    } else {
                        $res = \Yii::$app->db->createCommand("UPDATE codes
                                                                  SET 
                                                                    generation_uid = :GENID,
                                                                    parent_code = :PID, 
                                                                    childrens = :CHILD, 
                                                                    flag = :FLAG, 
                                                                    ucnt = :UCNT, 
                                                                    product_uid = nullif(:PROD,0),
                                                                    release_date = :DATE,
                                                                    activate_date = :DATE2,
                                                                    object_uid = :OBJ,
                                                                    lmtime = :LMTIME
                                                WHERE id =" . intval($obj["id"])
                            , [
                                ':GENID'  => $obj["generation_uid"],
                                ':PID'    => $obj["parent_code"],
                                ':CHILD'  => $obj["childrens"],
                                ':FLAG'   => $obj["flag"],
                                ':UCNT'   => $obj["ucnt"],
                                ':PROD'   => $obj["product_uid"],
                                ':DATE'   => $obj["release_date"],
                                ':DATE2'  => $obj["activate_date"],
                                ':OBJ'    => $obj["object_uid"],
                                ':LMTIME' => $obj["lmtime"],
                            ])->execute();
//file_put_contents("/srv/www/itrack-rf2-api.dev-og.com/runtime/sda", "апдейт\n".print_r($res,true), FILE_APPEND);
                        
                        //хистори
                        $history = json_decode($obj["history"]);
                        if (is_object($history) || is_array($history)) {
                            foreach ($history as $el) {
//file_put_contents("/srv/www/itrack-rf2-api.dev-og.com/runtime/sda", "апдейт\n".print_r($el,true), FILE_APPEND);
                                if (is_object($el)) {
                                    $el = get_object_vars($el);
                                    /*
                                                                $rrr = \Yii::$app->db->createCommand("SELECT * FROM history WHERE
                                                                                    created_at=:created_at and
                                                                                    operation_uid=:operation_uid and
                                                                                    code_uid=:code_uid and
                                                                                    created_by=:created_by and
                                                                                    coalesce(data,'')=coalesce(:data,'')"
                                                                                    , [
                                                                                    ':created_at' => $el["created_at"],
                                                                                    ':operation_uid' => $el["operation_uid"],
                                                                                    ':code_uid' => $el["code_uid"],
                                                                                    ':created_by' => $el["created_by"],
                                                                                    ':data' => $el["data"],
                                                                                ])->queryAll();
                                                                if(!count($rrr))*/
                                    $rrr = \Yii::$app->db->createCommand("INSERT INTO history (created_at,operation_uid,code_uid,created_by,data,object_uid,product_uid,address,comment,shopname,content,invoice_uid)
                                                    VALUES (:created_at,:operation_uid,:code_uid,:created_by,:data,:object_uid,:product_uid,:address,:comment,:shopname,:content,:invoice_uid)"
                                        , [
                                            ':created_at'    => $el["created_at"],
                                            ':operation_uid' => $el["operation_uid"],
                                            ':code_uid'      => $el["code_uid"],
                                            ':created_by'    => $el["created_by"],
                                            ':data'          => $el["data"],
                                            ':object_uid'    => $el["object_uid"],
                                            ':product_uid'   => (!empty($el["product_uid"]) ? $el["product_uid"] : null),
                                            ':address'       => (!empty($el["address"]) ? $el["address"] : null),
                                            ':comment'       => (!empty($el["comment"]) ? $el["comment"] : null),
                                            ':shopname'      => (!empty($el["shopname"]) ? $el["shopname"] : null),
                                            ':content'       => (!empty($el["content"]) ? $el["content"] : null),
                                            ':invoice_uid'   => (!empty($el["invoice_uid"]) ? $el["invoice_uid"] : null),
                                        ])->execute();
                                }
                            }
                        }
                    }
                    if ($res !== false) {
                        $ids[] = $obj['id'];
                    }
                }
            }
        }
        
        //генерации
        $request = json_decode(\Yii::$app->getRequest()->getBodyParam('request_gen'));
        if (is_array($request)) {
            foreach ($request as $obj) {
                $obj = get_object_vars($obj);
                // @todo проверка, а могут ли приходить изменения кода с данного объекта
                //
                $record = \Yii::$app->db->createCommand("SELECT * FROM generations WHERE id = '" . $obj["id"] . "'")->queryOne();
                if (!empty($record)) {
                    if ($record["lmtime"] > $obj["lmtime"]) {
                        //запись в нашей базе новее, чем присланный с объекта - закидываем в журнал конфликтов
                        
                        $conflict = new Conflicts();
                        $conflict->load([
                            'typeof'     => 'generations',
                            'oldvalue'   => json_encode($obj),
                            'newvalue'   => json_encode($record),
                            'object_uid' => 1//\Yii::$app->user->getIdentity()->id,
                        ], '');
                        $res = $conflict->save();
                    } else {
                        $res = \Yii::$app->db->createCommand("UPDATE generations SET
                                                                        created_at = :a1,
                                                                        cnt = :a2,
                                                                        codetype_uid = :a3,
                                                                        capacity = :a4,
                                                                        prefix = :a5,
                                                                        status_uid = :a6,
                                                                        created_by = :a7,
                                                                        deleted_at = :a8,
                                                                        deleted_by = :a9,
                                                                        comment = :a10,
                                                                        product_uid = :a11,
                                                                        object_uid = :a12,
                                                                        is_rezerv = :a13,
                                                                        originalid = :a14,
                                                                        lmtime = :lmtime,
                                                                        cnt_src = :a15,
                                                                        save_cnt = :a16,
                                                                        parent_uid = :a17,
                                                                        packing_status = :a18,
                                                                        equip_uid = :a19,
                                                                        data_uid = :a20
                                            WHERE id ='" . $obj["id"] . "'"
                            , [
                                ':a1'     => $obj["created_at"],
                                ':a2'     => $obj["cnt"],
                                ':a3'     => $obj["codetype_uid"],
                                ':a4'     => $obj["capacity"],
                                ':a5'     => $obj["prefix"],
                                ':a6'     => $obj["status_uid"],
                                ':a7'     => $obj["created_by"],
                                ':a8'     => $obj["deleted_at"],
                                ':a9'     => $obj["deleted_by"],
                                ':a10'    => $obj["comment"],
                                ':a11'    => $obj["product_uid"],
                                ':a12'    => $obj["object_uid"],
                                ':a13'    => $obj["is_rezerv"],
                                ':a14'    => $obj["originalid"],
                                ':a15'    => $obj["cnt_src"],
                                ':a16'    => $obj["save_cnt"],
                                ':a17'    => $obj["parent_uid"],
                                ':lmtime' => $obj["lmtime"],
                                ':a18'    => $obj["packing_status"],
                                ':a19'    => $obj["equip_uid"],
                                ':a20'    => $obj["data_uid"],
                            ])->execute();
                    }
                } else {
                    //INSERT INTO
                    $res = \Yii::$app->db->createCommand("INSERT INTO generations (id,created_at,cnt,codetype_uid,capacity,prefix,status_uid,created_by,deleted_at,deleted_by,comment,product_uid,object_uid,is_rezerv,originalid,cnt_src,save_cnt,num,lmtime,parent_uid,packing_status,equip_uid,data_uid)
                                                                        VALUES (:a0,:a1,:a2,:a3,:a4,:a5,:a6,:a7,:a8,:a9,:a10,:a11,:a12,:a13,:a14,:a15,:a16,:a17,:lmtime,:a18, :a19, :a20, :a21)
                                            "
                        , [
                            ':a0'     => $obj["id"],
                            ':a1'     => $obj["created_at"],
                            ':a2'     => $obj["cnt"],
                            ':a3'     => $obj["codetype_uid"],
                            ':a4'     => $obj["capacity"],
                            ':a5'     => $obj["prefix"],
                            ':a6'     => $obj["status_uid"],
                            ':a7'     => $obj["created_by"],
                            ':a8'     => $obj["deleted_at"],
                            ':a9'     => $obj["deleted_by"],
                            ':a10'    => $obj["comment"],
                            ':a11'    => $obj["product_uid"],
                            ':a12'    => $obj["object_uid"],
                            ':a13'    => $obj["is_rezerv"],
                            ':a14'    => $obj["originalid"],
                            ':a15'    => $obj["cnt_src"],
                            ':a16'    => $obj["save_cnt"],
                            ':a17'    => $obj["num"],
                            ':a18'    => $obj["parent_uid"],
                            ':lmtime' => $obj["lmtime"],
                            ':a19'    => $obj["packing_status"],
                            ':a20'    => $obj["equip_uid"],
                            ':a21'    => $obj["data_uid"],
                        ])->execute();
                }
                if ($res !== false) {
                    $ids[] = $obj['id'];
                }
            }
        }
        
        
        //накладные
        $request = json_decode(\Yii::$app->getRequest()->getBodyParam('request_invoices'));
        
        if (is_array($request)) {
            foreach ($request as $obj) {
                $obj = get_object_vars($obj);
                // @todo проверка, а могут ли приходить изменения кода с данного объекта
                //
                $record = \Yii::$app->db->createCommand("SELECT * FROM invoices WHERE id = '" . $obj["id"] . "'")->queryOne();
                if (!empty($record)) {
                    if ($record["lmtime"] > $obj["lmtime"]) {
                        //запись в нашей базе новее, чем присланный с объекта - закидываем в журнал конфликтов
                        
                        $conflict = new Conflicts();
                        $conflict->load([
                            'typeof'     => 'invoices',
                            'oldvalue'   => json_encode($obj),
                            'newvalue'   => json_encode($record),
                            'object_uid' => \Yii::$app->user->getIdentity()->id,
                        ], '');
                        $res = $conflict->save();
                    } else {
                        $res = \Yii::$app->db->createCommand("UPDATE invoices SET
                                                                      invoice_number = :a1,
                                                                      invoice_date = :a2,
                                                                      codes = :a3,
                                                                      created_by = :a4,
                                                                      created_at = :a5,
                                                                      is_gover = :a6,
                                                                      dest_address = :a7,
                                                                      object_uid = :a8,
                                                                      dest_consignee = :a9,
                                                                      dest_settlement = :a10,
                                                                      updated_by = :a11,
                                                                      updated_at = :a12,
                                                                      newobject_uid = :a13,
                                                                      realcodes = :a14,
                                                                      content = :a15,
                                                                      typeof = :a16,
                                                                      dest_fns = :a17
                                              WHERE id ='" . $obj["id"] . "'"
                            , [
                                ':a1'  => $obj["invoice_number"],
                                ':a2'  => $obj["invoice_date"],
                                ':a3'  => $obj["codes"],
                                ':a4'  => $obj["created_by"],
                                ':a5'  => $obj["created_at"],
                                ':a6'  => $obj["is_gover"],
                                ':a7'  => $obj["dest_address"],
                                ':a8'  => $obj["object_uid"],
                                ':a9'  => $obj["dest_consignee"],
                                ':a10' => $obj["dest_settlement"],
                                ':a11' => $obj["updated_by"],
                                ':a12' => $obj["updated_at"],
                                ':a13' => $obj["newobject_uid"],
                                ':a14' => $obj["realcodes"],
                                ':a15' => $obj["content"],
                                ':a16' => $obj["typeof"],
                                ':a17' => $obj["dest_fns"],
                            ])->execute();
                    }
                } else {
                    //INSERT INTO
                    $res = \Yii::$app->db->createCommand("INSERT INTO invoices (id,invoice_number,invoice_date,codes,created_by,created_at,is_gover,dest_address,object_uid,dest_consignee,dest_settlement,updated_by,updated_at,newobject_uid,realcodes,content,typeof,dest_fns)
                                                                        VALUES (:a0,:a1,:a2,:a3,:a4,:a5,:a6,:a7,:a8,:a9,:a10,:a11,:a12,:a13,:a14,:a15,:a16,:a17)
                                            "
                        , [
                            ':a0'  => $obj["id"],
                            ':a1'  => $obj["invoice_number"],
                            ':a2'  => $obj["invoice_date"],
                            ':a3'  => $obj["codes"],
                            ':a4'  => $obj["created_by"],
                            ':a5'  => $obj["created_at"],
                            ':a6'  => $obj["is_gover"],
                            ':a7'  => $obj["dest_address"],
                            ':a8'  => $obj["object_uid"],
                            ':a9'  => $obj["dest_consignee"],
                            ':a10' => $obj["dest_settlement"],
                            ':a11' => $obj["updated_by"],
                            ':a12' => $obj["updated_at"],
                            ':a13' => $obj["newobject_uid"],
                            ':a14' => $obj["realcodes"],
                            ':a15' => $obj["content"],
                            ':a16' => $obj["typeof"],
                            ':a17' => $obj["dest_fns"],
                        ])->execute();
                }
                if ($res !== false) {
                    $ids[] = $obj['id'];
                }
            }
        }
        
        return ['status' => true, 'user' => \Yii::$app->user->getIdentity(), 'ids' => $ids];
    }
}
