<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 13.05.15
 * Time: 11:49
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Check;
use app\modules\itrack\models\Code;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\Constant;
use app\modules\itrack\models\History;
use app\modules\itrack\models\HistoryData;
use app\modules\itrack\models\Message;
use yii\data\SqlDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\rest\Controller;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;

/**
 * @OA\Get(
 *  path="/code/{id}",
 *  tags={"Коды"},
 *  description="Получение данных кода",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Код",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Code")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/code/{id}/full",
 *  tags={"Коды"},
 *  description="Полная проверка кода",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Код",
 *      @OA\JsonContent(
 *          @OA\Property(property="codes", ref="#/components/schemas/app_modules_itrack_models_Code"),
 *          @OA\Property(property="historyCheckMan", type="object", example=null),
 *          @OA\Property(property="historyLastOutCome", type="object", example=null),
 *          @OA\Property(property="historyLastView", type="array", @OA\Items()),
 *          @OA\Property(property="message", type="string", example=""),
 *          @OA\Property(property="history", type="array", @OA\Items()),
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/code/{id}/individual",
 *  tags={"Коды"},
 *  description="Получение данных индивидуального кода",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Код",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Code_Individual")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/code/{id}/codes",
 *  tags={"Коды"},
 *  description="Получение содержимого группового кода",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Код",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Code_Group")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class CodeController extends Controller
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Code';
    
    static function find_childs($code, $codes)
    {
        $c = [];
        foreach ($codes as $k => $cc) {
            if ($cc["parent_code"] == $code) {
                if ($cc["codetype"] == "Групповой") {
                    $cc["childs"] = self::find_childs($cc["code"], $codes);
                } else {
                    $cc["childs"] = [];
                }
                $c[] = $cc;
            }
        }
        
        return $c;
    }
    
    public function authExcept()
    {
        return ['check'];
    }
    
    /**
     * Валидация кодов по заданной операции
     *
     * @return type
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionInfo()
    {
        if (Constant::get('useForeignCodes') === 'enabled') {
            $codes = \Yii::$app->request->getBodyParam('codes');

            $generation = Generation::findOne(['id' => \Yii::$app->request->post('generationUid')]);
            $nomenclature = $generation->product->nomenclature;

            $errorGtinCodes = [];
            $errors = [];

            foreach($codes as $code)
            {
                if(!preg_match('#^01'.$nomenclature->gtin.'.*#si', $code)) {
                    $errorGtinCodes[] = Code::stripCode($code);
                }
            }

            if (count($errorGtinCodes) > 0) {
                $errors[] = 'GTIN кода не совпадает с GTIN заказа: ' . implode(',', $errorGtinCodes);
            }

            if(count($errors) > 0) {
                throw new \yii\web\BadRequestHttpException(implode("|", $errors));
            }

            $cleanCodes = [];

            foreach ($codes as $code) {
                $cleanCodes[] =  Code::stripCode($code);
            }

            $codesString = '(';

            foreach ($cleanCodes as $code) {
                $codesString .=  \Yii::$app->db->quoteValue($code) . ',';
            }

            $codesString = rtrim($codesString, ',') . ')';

            $codesData = \Yii::$app->db->createCommand('SELECT * FROM codes WHERE flag=1 AND code IN ' . $codesString)->queryAll();

            if (!empty($codesData)) {
                $errorCodes = ArrayHelper::getColumn($codesData, 'code');
                $errors[] = 'В списке кодов присутствуют уже агрегированные: ' . implode(',', $errorCodes);
                throw new \yii\web\BadRequestHttpException(implode("|", $errors));
            }

            return ["status" => 200, "Message" => "Ok"];
        }
        
        $perms = ["codeFunction-pack",
            "codeFunction-pack-full",
            "codeFunction-paleta",
            "codeFunction-paleta-uni",
            "codeFunction-l3",
            "codeFunction-l3-uniform"
            ,
            "codeFunction-l3-add",
            "codeFunction-l3-add-uniform",
            "codeFunction-paleta-add",
            "codeFunction-paleta-add-uniform",
            "codeFunction-gofra-add",
            "codeFunction-gofra-add-uniform",
        ];
        $ret = [];
        $errors = [
            "Коды не найдены"       => [],
            "Некорректная серия"    => [],
            "Некорректный тип кода" => [],
        ];
        $has_error = false;
        
        $codes = \Yii::$app->request->getBodyParam('codes');
        if (empty($codes)) {
            throw new \yii\web\BadRequestHttpException('Не задан список кодов');
        }
        
        $series = \Yii::$app->request->getBodyParam("series");
        $operation_name = \Yii::$app->request->getBodyParam("operation");
        if (!empty($operation_name)) {
            $operation = \Yii::$app->authManager->getPermission($operation_name);
        }
        $performed = false;   //для л3 - операции подменяются - соответственно подменять только 1 раз
        
        $codes_src = [];
        foreach ($codes as $k => $code) {
            $codes[$k] = Code::stripCode($code);
            $codes_src[Code::stripCode($code)] = 1;
        }
        
        $result = \Yii::$app->db->createCommand("SELECT codes.*,product.series,generations.codetype_uid,
                                                    is_empty(codes.flag) as empty,
                                                    is_removed(codes.flag) as removed,
                                                    is_retail(codes.flag) as retail,
                                                    is_released(codes.flag) as released,
                                                    is_defected(codes.flag) as defected,
                                                    is_returned(codes.flag) as returned,
                                                    is_gover(codes.flag) as gover,
                                                    is_blocked(codes.flag) as blocked,
                                                    is_paleta(codes.flag) as paleta,
                                                    is_l3(codes.flag) as l3,
                                                    is_serialized(codes.flag) as serialized,
                                                    is_brak(codes.flag) as brak,
                                                    nomenclature.hasl3
                                        FROM codes
                                        LEFT JOIN product ON codes.product_uid=product.id
                                        LEFT JOIN nomenclature ON nomenclature.id = product.nomenclature_uid
                                        LEFT JOIN generations ON generation_uid = generations.id
                                        WHERE code=ANY(:codes)", [":codes" => \app\modules\itrack\components\pghelper::arr2pgarr($codes)])->queryAll();
        foreach ($result as $res) {
            unset($codes_src[$res["code"]]);
            if (isset($series) && !empty($series) && $series != $res["series"]) {
                $has_error = true;
                $str = "Пачки из другой серии \"" . $res["series"] . "\" вместо \"" . $series . "\"";
                if (isset($errors[$str])) {
                    $errors[$str][] = $res["code"];
                } else {
                    $errors[$str] = [$res["code"]];
                }
            }
            if (!empty($operation)) {
                //фигня с бандеролями
                if (Constant::get('hasL3') == 'true' && !$performed) {
                    switch ($operation_name) {
                        case "codeFunction-pack":
                            if ($res["codetype_uid"] == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
                                $operation_name = "codeFunction-paleta";
                                $operation = \Yii::$app->authManager->getPermission($operation_name);
                                $performed = true;
                            } else {
                                if ($res["hasl3"]) {
                                    $errors["Некорректный тип кода"][] = $c;
                                }
                            }
                            break;
                        case "codeFunction-pack-full":
                            if ($res["codetype_uid"] == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
                                $operation_name = "codeFunction-paleta-uni";
                                $operation = \Yii::$app->authManager->getPermission($operation_name);
                                $performed = true;
                            } else {
                                if ($res["hasl3"]) {
                                    $errors["Некорректный тип кода"][] = $c;
                                }
                            }
                            break;
                        case "codeFunction-paleta":
                        case "codeFunction-paleta-uni":
                            $performed = true;
                            break;
                        case "codeFunction-l3":
                        case "codeFunction-l3-uniform":
                            if ($res["codetype_uid"] == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
                                $errors["Некорректный тип кода"][] = $c;
                            } else {
                                if ($res["hasl3"]) {
                                    $performed = true;
                                } else {
                                    $errors["Некорректный тип кода"][] = $c;
                                }
                            }
                            break;
                    }
                }
                
                
                $r = \app\modules\itrack\models\Role::checkPermission($operation, \Yii::$app->user->getIdentity(), $res,
                    in_array($operation_name, $perms) ? 1 : 0);
                if ($r !== true) {
                    $has_error = true;
                    foreach ($r as $e => $c) {
                        if (isset($errors[$e])) {
                            $errors[$e][] = $c;
                        } else {
                            $errors[$e] = [$c];
                        }
                    }
                }
            }
        }
        if (count($codes_src)) {
            $has_error = true;
            $errors["Коды не найдены"] = array_keys($codes_src);
        }
        
        if ($has_error) {
            foreach ($errors as $k => $v) {
                if (count($v)) {
                    $ret[] = $k . ": " . implode(",", $v);
                }
            }
            throw new \yii\web\BadRequestHttpException(implode("|", $ret));
        } else {
            return ["status" => 200, "Message" => "Ok"];
        }
    }
    
    /**
     * Получение аксаптой истории по заданным кодам
     *
     * @return type
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionAx()
    {
        $ret = [];
        $codes = \Yii::$app->request->getBodyParam('codes');
        if (empty($codes)) {
            throw new \yii\web\BadRequestHttpException('Не задан список кодов');
        }
        foreach ($codes as $code) {
            $cdata = [
                'code' => $code,
            ];
            $code = Code::stripCode($code);
            $cinfo = Code::findOneByCode($code);
            if (empty($cinfo) || $cinfo->generation->codetype_uid == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
                $cdata["result"] = false;
            } else {
                $cdata["sgtin"] = ($cinfo->product->nomenclature->gtin ?? '') . $cinfo->code;
                $cdata["result"] = true;
                $cdata["series"] = $cinfo->product->series;
                $cdata["history"] = \Yii::$app->db->createCommand("
                    select
                        history.created_at,
                        history_operation.name,
                        history.data,
                        invoice_number,
                        invoice_date,
                        dest_address,
                        dest_consignee,
                        dest_settlement,
                        dest_kpp,
                        dest_inn,
                        cust_name,
                        cust_address,
                        objects.name as srcobject,
                        objects.address as srcaddress,
                        newobj.name as dstobject,
                        newobj.address as dstaddress
                            from _get_code_history(:code,'{}',999)as history
                            left join history_operation on history_operation.id = history.operation_uid
                            left join invoices on history.invoice_uid = invoices.id
                            left join objects on invoices.object_uid = objects.id
                            left join objects as newobj on invoices.newobject_uid = newobj.id
                            WHERE history.operation_uid != 11
                            order by history.created_at
                    ", [
                    ":code" => $code,
                ])->queryAll();
            }
            
            $ret[] = $cdata;
        }
        
        return ["response" => $ret];
    }
    
    public function actionIsm($code)
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotAcceptableHttpException("Эта функция не работает на сервере склада");
        }
        
        $code = Code::stripCode($code, true);
        $user = \Yii::$app->user->getIdentity();
        $connectionId = isset($user->object->uso_uid) ? $user->object->uso_uid : \Yii::$app->params['ism']['default'];
        \Yii::getLogger()->log('Запрос информации по коду: ' . $code, \yii\log\Logger::LEVEL_INFO, 'ism');
        $ism = new \app\modules\itrack\components\ISMarkirovka($connectionId);
        $resp = $ism->getCodeInfo($code);
        \Yii::getLogger()->log('Ответ: ' . print_r($resp, true), \yii\log\Logger::LEVEL_INFO, 'ism');
        try {
            $xml = new \SimpleXMLElement($resp[0]);
            foreach ($xml as $k => $xml_part) {
                break;
            }
            if ($k == 'kiz_info' && $xml_part->result->found == "true") {
                $is_l3 = false;
                $is_palleta = false;
                if (strlen($code) == 18) {
                    $grp = 0;
                    $ind = 0;
                    $products = [];
                    $tree = [];
                    foreach ($xml_part->sscc_down->tree as $el) {
                        if (isset($el->sscc)) {
                            $is_palleta = true;
                            $grp++;
                            $tree[(string)$el->parent_sscc]["childrens"][] = (string)$el->sscc;
                            $tree[(string)$el->parent_sscc]["code"] = (string)$el->parent_sscc;
                            if ((string)$el->parent_sscc != $code) {
                                $is_l3 = true;
                            }
                        } elseif (isset($el->sgtin)) {
                            $ind++;
                            $key = $el->sgtin->info_sgtin->status . "/" . $el->sgtin->info_sgtin->gtin . "/" . $el->sgtin->info_sgtin->series_number . "/" . $el->sgtin->info_sgtin->expiration_date . "/" . $el->sgtin->info_sgtin->tnved_code;
                            if (isset($products[$key])) {
                                $products[$key]["cnt"]++;
                            } else {
                                $products[$key] = [
                                    "cnt"             => 1,
                                    "status"          => (string)$el->sgtin->info_sgtin->status,
                                    "gtin"            => (string)$el->sgtin->info_sgtin->gtin,
                                    "series"          => (string)$el->sgtin->info_sgtin->series_number,
                                    "tnved"           => (string)$el->sgtin->info_sgtin->tnved_code,
                                    "expiration_date" => (string)$el->sgtin->info_sgtin->expiration_date,
                                ];
                            }
                            $tree[(string)$el->parent_sscc]["childrens"][] = (string)$el->sgtin->info_sgtin->sgtin;
                            $tree[(string)$el->parent_sscc]["code"] = (string)$el->parent_sscc;
                        }
                    }
                    $result = [
                        "code"         => $code,
                        "codeType"     => "Групповой",
                        "codeType_uid" => \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP,
//                        "is_palleta" => (\app\modules\itrack\models\Constant::get('hasL3') == 'true')?($is_l3?"Паллета":($is_paleta?"Короб":"Бандероль")): ($is_palleta ? "Паллета" : "Короб"),
                        "is_palleta"   => ($is_l3 ? "Паллета" : ($is_paleta ? "Паллета" : "Короб")),
                        "grpcnt"       => $grp,
                        "indcnt"       => $ind,
                        "products"     => array_values($products),
                        "tree"         => array_values($tree),
                    ];
                    //;
                } else {
                    $result = [
                        "code"            => $code,
                        "codeType"        => "Индивидуальный",
                        "codeType_uid"    => \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL,
                        "status"          => (string)$xml_part->sgtin->info_sgtin->status,
                        "parrent"         => (string)$xml_part->sgtin->info_sgtin->sscc,
                        "gtin"            => (string)$xml_part->sgtin->info_sgtin->gtin,
                        "series"          => (string)$xml_part->sgtin->info_sgtin->series_number,
                        "tnved"           => (string)$xml_part->sgtin->info_sgtin->tnved_code,
                        "expiration_date" => (string)$xml_part->sgtin->info_sgtin->expiration_date,
                    ];
                }
            } else {
                throw new NotFoundHttpException('Код не найден');
            }
            
            if (isset($resp[1]) && !empty($resp[1])) {
                $xml = new \SimpleXMLElement($resp[1]);
            }
            foreach ($xml as $k => $xml_part) {
                break;
            }
            if ($k == 'kiz_info' && $xml_part->result->found == "true") {
                if (isset($xml_part->sscc_up->info->sscc)) {
                    $result["parrent"] = (string)$xml_part->sscc_up->info->sscc; //опечатка - надо править ТСД. оно тут ловит (
                    $result["parent"] = (string)$xml_part->sscc_up->info->sscc;
                    if (!$is_l3 && $is_paleta) {
                        $result["is_palleta"] = "Короб с бандеролями";
                    }
                    if (!$is_l3 && !$is_paleta) {
                        if (isset($xml_part->sscc_up->info[1]->sscc)) {
                            $result["is_palleta"] = "Бандероль";
                            if (1 == $xml_part->sscc_up->info[1]->level) {
                                $result["parent_parent"] = (string)$xml_part->sscc_up->info[1]->sscc;
                            } else {
                                $result["parent"] = (string)$xml_part->sscc_up->info[1]->sscc;
                                $result["parent_parent"] = (string)$xml_part->sscc_up->info->sscc;
                            }
                        } else {
                            $result["is_palleta"] = "Короб";
                        }
                    }
                }
            } else {
                throw new NotFoundHttpException('Код не найден');
            }
        } catch (\Exception $ex) {
            throw new \yii\web\BadRequestHttpException('Не удалось получить данные по коду');
        }
        
        return $result;
    }
    
    public function actionLevels()
    {
        $levels = \Yii::$app->params["packNames"]["ShortLevels"] ?? Code::$ShortLevels;
        if (\app\modules\itrack\models\Constant::get('hasL3') == 'true') {
            $levels = \Yii::$app->params["packNames"]["ExtLevels"] ?? Code::$ExtLevels;
        }
        
        return ['levels' => $levels];
    }
    
    public function actionSimple($code)
    {
        $mainCode = Code::findOneByCode($code);
        if (!$mainCode) {
            throw new NotFoundHttpException("Код не найден", 403);
        }
        
        //$extraParams = $this->extraParams();
//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Простая проверка кода: $code", [['field'=>'Код','value'=>$code]]);
        
        return [
            'code' => $mainCode->toArray(['code', 'statusMessage', 'childs', 'status', 'codeType', 'product', 'object_uid', 'release_date', 'activate_date', 'parent_code'], ['generation_uid', 'contentByProduct'], true),
//            'message' => $mainCode->getStatusMessage(false),
        ];
    }
    
    /**
     * Проверка кода внутренними системами
     *
     * @param $code
     *
     * @return Code
     * @throws NotFoundHttpException
     */
    public function actionView($code, $address = null, $shopname = null)
    {
        $mainCode = Code::findOneByCode($code);
        if (!$mainCode) {
            throw new NotFoundHttpException("Код не найден", 403);
        }
        
        $historyCheckMan = null;
        $historyLastView = null;
        $historyLastOutCome = null;
        $history = null;
        
        if (\Yii::$app->user->can('user-controller') && \Yii::$app->user->can('codeFunction-checkMan') && SERVER_RULE != SERVER_RULE_SKLAD) {
            $historyCheckMan = History::createHistoryByOperation11($mainCode, \Yii::$app->user->getIdentity());
            if ($historyCheckMan) {
                if ($address || $shopname) {
                    $historyCheckMan->setScenario('checkManComment');
                    
                    $historyCheckMan->load(['address' => $address, 'shopname' => $shopname], '');
                    if ($historyCheckMan->save()) {
                        $historyCheckMan->refresh();
                    }
                }
                
                $historyCheckMan = $historyCheckMan->toArray([], ['historyOperation']);
            }
            
            $historyLastView = ($mainCode->historyLastView) ? array_map(function ($item) {
                return $item->toArray([], ['historyOperation', 'historyData']);
            }, $mainCode->historyLastView) : [];
        }
        
        $res = $mainCode->historyLastOutCome;
        if (is_array($res)) {
            $historyLastOutCome = $res;
        } else {
            $historyLastOutCome = ($res) ? $res->toArray([], ['historyOperation']) : null;
        }
        
        $history = ($mainCode->history) ? array_map(function ($history) {
            return $history->toArray([], ['historyOperation']);
        }, $mainCode->history) : [];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            if (!empty($mainCode->cachehistory) && is_array(json_decode($mainCode->cachehistory))) {
                $c = array_map(function ($v) {
                    $arr = [];
                    $v = get_object_vars($v);
                    $arr["uid"] = 0;
                    $arr["code_uid"] = $v["code_uid"];
                    $arr["created_at"] = $v["created_at"];
                    $arr["created_by"] = $v["created_by"];
                    $arr["operation_uid"] = $v["operation_uid"];
                    $arr["historyData"] = [
                        "data"        => $v["data"],
                        "comment"     => (isset($v["comment"]) ? $v["comment"] : null),
                        "content"     => (isset($v["content"]) ? $v["content"] : null),
                        "address"     => (isset($v["address"]) ? $v["address"] : null),
                        "shopname"    => (isset($v["shopname"]) ? $v["shopname"] : null),
                        "invoice_uid" => (isset($v["invoice_uid"]) ? $v["invoice_uid"] : null),
                        "product_uid" => (isset($v["product_uid"]) ? $v["product_uid"] : null),
                        "object_uid"  => $v["object_uid"],
                        "object"      => ((!empty($v["object_uid"])) ? \app\modules\itrack\models\Facility::find()->andWhere(["id" => $v["object_uid"]])->one() : null),
                        "invoice"     => ((!empty($v["invoice_uid"])) ? \app\modules\itrack\models\Invoice::find()->andWhere(["id" => $v["invoice_uid"]])->one() : null),
                        "product"     => ((!empty($v["product_uid"])) ? \app\modules\itrack\models\Product::find()->andWhere(["id" => $v["product_uid"]])->one() : null),
                    ];
                    $arr["historyOperation"] = \app\modules\itrack\models\HistoryOperation::find()->andWhere(["id" => $v["operation_uid"]])->one();
                    
                    return $arr;
                },
                    json_decode($mainCode->cachehistory)
                );
                function checkHis($need, $arr)
                {
                    $ret = false;
                    foreach ($arr as $v) {
                        if (($v["code_uid"] == $need["code_uid"])
                            && (\Yii::$app->formatter->asDatetime($v["created_at"]) == \Yii::$app->formatter->asDatetime($need["created_at"]))
                            && ($v["created_by"] == $need["created_by"])
                            && ($v["operation_uid"] == $need["operation_uid"])
                        ) {
                            return true;
                        }
                    }
                    
                    return $ret;
                }
                foreach ($c as $k => $v) {
                    if (checkHis($v, $history)) {
                        unset($c[$k]);
                    }
                }
                $c = array_reverse($c);
                $history = array_merge($c, $history);
            }
        }
        $extraParams = $this->extraParams();

//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Полная проверка кода: $code", [['field' => 'Код', 'value' => $code]]);
        return [
            'code'               => $mainCode->toArray([], $extraParams),
//            'childGroup' => $mainCode->getChildGroup(false),
            'historyCheckMan'    => $historyCheckMan,
            'historyLastOutCome' => $historyLastOutCome,
            'historyLastView'    => $historyLastView,
            'message'            => $mainCode->getStatusMessage(false),
            'history'            => $history,
        ];
    }
    
    /**
     * @param $code
     *
     * @return \yii\db\Command
     * @throws NotFoundHttpException
     */
    public function actionViewIndividual($code)
    {
        $mainCode = Code::findOneByCode($code);
        if (!$mainCode) {
            throw new NotFoundHttpException("Код не найден", 403);
        }
        
        $this->serializer = [
            'class'              => 'app\modules\itrack\components\boxy\Serializer',
            'collectionEnvelope' => 'individualCodes',
        ];
//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Просмотр содержимого кода: $code", [['field' => 'Код', 'value' => $code]]);
        $pagination = $mainCode->getChildIndividual();
        
        return $pagination;
    }
    
    public function actionViewChildNew($code)
    {
        $mainCode = Code::findOneByCode($code);
        if (!$mainCode) {
            throw new NotFoundHttpException("Код не найден", 403);
        }
        
        $codes = $mainCode->getContent();
//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Просмотр содержимого кода: $code", [['field' => 'Код', 'value' => $code]]);
        
        $c = [];
        foreach ($codes as $k => $cc) {
            if ($cc["parent_code"] == $code) {
                if ($cc["codetype"] == "Групповой") {
                    $cc["childs"] = self::find_childs($cc["code"], $codes);
                } else {
                    $cc["childs"] = [];
                }
                $c[] = $cc;
            }
        }
        
        return ["codes" => $c];
    }
    
    public function actionViewChild($code)
    {
        $mainCode = Code::findOneByCode($code);
        if (!$mainCode) {
            throw new NotFoundHttpException("Код не найден", 403);
        }
        
        $this->serializer = [
            'class'              => 'app\modules\itrack\components\boxy\Serializer',
            'collectionEnvelope' => 'codes',
        ];
        $dataProvider = new SqlDataProvider([
            'sql' => $mainCode->getChild(),
        ]);
        
        $models = $dataProvider->getModels();
        $items = [];
        foreach ($models as $model) {
            $m = new Code();
            $m->load($model, '');
            $items[] = $m;
        }
        $dataProvider->setModels($items);
        
        return $dataProvider;
    }
    
    /**
     * Проверка кода потребителем
     *
     * @param $code
     *
     * @return Code
     * @throws NotFoundHttpException|NotAcceptableHttpException
     */
    public function actionCheck($code, $lat = 0, $lon = 0)
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotAcceptableHttpException("This function not work on sklad");
        }
        
        /** @var Code $modelClass */
        $modelClass = $this->modelClass;
        
        /**
         * 1. Мобильное приложение
         * 2. Горячая линия
         *
         * @todo Вредрить определение источника
         */
        $checkSourceID = 1;
        
        // Address
        $address = \Yii::$app->geo->addressLookup($lat, $lon);
        
        // Generate check
        $check = new Check();
        $check->load([
            'txt'        => $code,
            'source_uid' => $checkSourceID,
            'lat'        => $lat,
            'lon'        => $lon,
            'address'    => $address,
        ], '');
        
        $message = Message::find()->andWhere(['name' => 'USER_CHECK_ERROR'])->one();
        /** @var Code $model */
        $model = $modelClass::findOneByCode($code);
        if (!$model) {
            if (!$check->save()) {
                \Yii::error(VarDumper::export([
                    'code'        => $code,
                    'checkErrors' => $check->getErrors(),
                ]), 'check');
                $check = null;
            }
            
            return [
                'code'    => null,
                'check'   => $check,
                'message' => ($message) ? $message->message : '',
            ];
        }
        
        // @todo Same action with code
        // update check count
        $model->ucnt++;
        $model->update(false, ['ucnt']);
        
        $check->load([
            'code_uid' => $model->id,
            'ucnt'     => $model->ucnt,
        ], '');
        
        if (!$check->save()) {
            \Yii::error(VarDumper::export([
                'code'        => $code,
                'checkErrors' => $check->getErrors(),
            ]), 'check');
            $check = null;
        }
        
        //взять сообщение из messages name=$state
        return [
            'code'    => $model,
            'check'   => $check,
            'message' => $model->statusMessage,
        ];
    }
    
    /**
     * @return HistoryData
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionComment()
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotAcceptableHttpException("This function not work on sklad");
        }
        
        if (!\Yii::$app->user->can('codeFunction-checkMan-comment')) {
            throw new NotAcceptableHttpException("Запрет на выполнение операции");
        }
        
        $historyId = \Yii::$app->getRequest()->getBodyParam('history_uid');
        
        
        /** @var History $history */
        $history = $this->findModel($historyId, History::class);
        
        $history->setScenario('checkManComment');
        
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_CODES, "Добавление комментария контроллером: " . $history->code, [["field" => "Код", "value" => $history->code], ["field" => "Адрес", "value" => \Yii::$app->request->getBodyParam('address')], ["field" => "Комментарий", "value" => \Yii::$app->request->getBodyParam('comment')]]);
        
        $history->load(\Yii::$app->getRequest()->getBodyParams(), '');
        if ($history->save()) {
            \Yii::$app->getResponse()->setStatusCode(201);
            
            return ['historyCheckMan' => $history->toArray([], ['historyOperation'])];
        }
        
        return $history;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
            case 'delete':
                if (!\Yii::$app->user->can('#@$@#$')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
            case 'viewIndividual':
            case 'viewChild':
                if (!\Yii::$app->user->can('report-code-data')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'simple':
                break;
            case 'ism':
                if (!\Yii::$app->user->can('codeFunction-ISM')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            default:
                throw new NotAcceptableHttpException("Запрет на выполнение операции");
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}