<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;

/**
 * @OA\Get(
 *  path="/info",
 *  tags={"Инфо"},
 *  description="Инфо",
 *  @OA\Response(
 *      response="200",
 *      description="Инфо",
 *      @OA\JsonContent(
 *          @OA\Property(property="conflicts", type="integer", example=1),
 *          @OA\Property(property="reserve", type="object", @OA\Items(
 *              @OA\Property(property="group", type="integer", example=100),
 *              @OA\Property(property="individual", type="integer", example=300),
 *          )),
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class InfoController extends ActiveController
{
    
    public $modelClass = 'app\modules\itrack\models\Menu';
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'info',
    ];
    
    public function actions()
    {
        return [];
    }
    
    public function actionHistory()
    {
        $ret = [];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            $params = \Yii::$app->request->getQueryParams();
            if (!isset($params["startDate"])) {
                $params["startDate"] = date("Y-m-d");
            }
            if (!isset($params["endDate"])) {
                $params["endDate"] = date("Y-m-d");
            }
            $ret = \Yii::$app->db->createCommand("SELECT * FROM cache_size WHERE date(created_at) between :a1 and :a2 ORDER by created_at", [":a1" => $params["startDate"], ":a2" => $params["endDate"]])->queryAll();
        }
        
        return ["history" => $ret];
    }
    
    public function actionIndex()
    {
        $ret = [];
        
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            //размер кеша
            $r = \Yii::$app->db->createCommand("SELECT count(*) as c,now()-min(lmtime) as t FROM codes_cache WHERE sended=false")->queryOne();
            $ret["cache"]["size"] = $r["c"];
            $ret["cache"]["age"] = $r["t"];
            //реплика?
            $r = \Yii::$app->db->createCommand("SELECT now()-min(last_generation) as t FROM cfg")->queryOne();
            $ret["replica"]["age"] = $r["t"];
        } else {
            //конфликты
            $r = \Yii::$app->db->createCommand("SELECT count(*) as c FROM conflicts")->queryOne();
            $ret["conflicts"] = $r["c"];
            
            $r = \Yii::$app->db->createCommand("SELECT codetype_uid,sum(cnt) as c FROM generations WHERE object_uid is null and is_rezerv=true GROUP by 1 ORDER by 1")->queryAll();
            $ret["reserve"]["group"] = 0;
            $ret["reserve"]["individual"] = 0;
            foreach ($r as $row) {
                if ($row["codetype_uid"] == 1) {
                    $ret["reserve"]["individual"] = $row["c"];
                } else {
                    $ret["reserve"]["group"] = $row["c"];
                }
            }
        }
        
        return $ret;
    }
    
}

