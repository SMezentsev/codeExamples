<?php
/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 01.03.2017
 * Time: 15:39
 */

namespace app\modules\itrack\models;

use yii\base\Model;

/**
 * @OA\Schema(schema="app_modules_itrack_models_Role_Type",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example=1),
 *          @OA\Property(property="name", type="string", example="Центр тестирования"),
 *      }
 * )
 */
class Role extends Model
{
    /**
     * Валидауия кода по пермишену
     *
     * @param \yii\rbac\Permission $perm
     * @param type                 $code
     *
     * @return type
     */
    static function checkPermission(\yii\rbac\Permission $perm, User $user, $code, $idx = 0)
    {
        $codes = $perm->data->codes[$idx];
        if ($codes->type == "group" && $code["codetype_uid"] != CodeType::CODE_TYPE_GROUP) {
            return ["Некорректный тип кода" => $code["code"]];
        }
        if ($codes->type == "individual" && $code["codetype_uid"] != CodeType::CODE_TYPE_INDIVIDUAL) {
            return ["Некорректный тип кода" => $code["code"]];
        }
        
        if (in_array($perm->name, ['codeFunction-pack', 'codeFunction-pack-full']) && $codes->type == "individual" && Constant::get('check_serialization') == 'true') {
            if (!$code["serialized"]) {
                return ['Код не прошел сериализацию' => $code["code"]];
            }
            if ($code["brak"]) {
                return ['Код был забракован' => $code["code"]];
            }
        }
        
        if (isset($codes->need)) {
            foreach ($codes->need as $p) {
                $i = explode(":", $p);
                switch ($i[0]) {
                    case "empty":
                        if (!$code["empty"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "object":
                        if ($user->object_uid != $code["object_uid"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "removed":
                        if (!$code["removed"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "retail":
                        if (!$code["retail"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "released":
                        if (!$code["released"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "outcome":
                        if (!$code["released"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "defected":
                        if (!$code["defected"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "returned":
                        if (!$code["returned"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "gover":
                        if (!$code["gover"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "blocked":
                        if (!$code["blocked"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "parent":
                        if (empty($code["parent_code"])) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "product":
                        if (empty($code["product_uid"])) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "paleta":
                        if (!$code["paleta"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "l3":
                        if (!$code["l3"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "ownprod":
                        break;
                    
                    default:
                        //тут составные и новые валидаторы.. пока игнор
                }
            }
        }
        
        if (isset($codes->except)) {
            foreach ($codes->except as $p) {
                $i = explode(":", $p);
                switch ($i[0]) {
                    case "empty":
                        if ($code["empty"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "object":
                        if ($user->object_uid == $code["object_uid"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "removed":
                        if ($code["removed"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "retail":
                        if ($code["retail"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "released":
                        if ($code["released"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "outcome":
                        if ($code["released"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "defected":
                        if ($code["defected"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "returned":
                        if ($code["returned"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "gover":
                        if ($code["gover"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "blocked":
                        if ($code["blocked"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "parent":
                        if (!empty($code["parent_code"])) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "product":
                        if (!empty($code["product_uid"])) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "paleta":
                        if ($code["paleta"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "l3":
                        if ($code["l3"]) {
                            return [$i[1] => $code["code"]];
                        }
                        break;
                    case "ownprod":
                        break;
                    
                    default:
                        //тут составные и новые валидаторы.. пока игнор
                }
            }
        }
        
        return true;
    }
}
