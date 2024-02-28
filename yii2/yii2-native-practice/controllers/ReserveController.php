<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 15.09.15
 * Time: 16:48
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\Facility;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationSort;
use app\modules\itrack\models\GenerationStatus;
use yii\web\MethodNotAllowedHttpException;

/**
 * @OA\Post(
 *   path="/rf/reserve",
 *   tags={"Резерв"},
 *   description="Создание резерва",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          example={
 *              @OA\Property(property="cnt", type="integer", example=10),
 *              @OA\Property(property="codetype_uid", type="integer", example=1),
 *              @OA\Property(property="object_uid", type="integer", example=2),
 *          }
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Резерв кодов",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/rf/reserve/magic",
 *  tags={"Резерв"},
 *  description="Состояние резерва",
 *  @OA\Response(
 *      response="200",
 *      description="Резерв кодов",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="reserves",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/rf/reserve/history",
 *  tags={"Резерв"},
 *  description="История резерва",
 *  @OA\Response(
 *      response="200",
 *      description="Резерв кодов",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="reserves",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/rf/reserve/count",
 *  tags={"Резерв"},
 *  description="Состояние резерва",
 *  @OA\Response(
 *      response="200",
 *      description="Резерв кодов",
 *      @OA\JsonContent(
 *          @OA\Property(property="message", type="string", example="Зарезервированно 200 Индивидуальный 100 Групповой"),
 *          @OA\Property(
 *              property="reserves",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Generation")
 *          ),
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class ReserveController extends \yii\rest\Controller
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Generation';
    
    public function actions()
    {
        $actions = parent::actions();
        
        unset($actions['delete']);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['update']);
        }
        
        return $actions;
    }
    
    /**
     * Кол-во зарезервированных кодов
     */
    public function actionCount()
    {
        $objectId = \Yii::$app->user->getIdentity()->object_uid;
        
        
        if (\Yii::$app->user->can('see-all-objects')) {
            $objectId = null;
        }
        
        /** @var Generation[] $reserves */
        $reserves = Generation::reserveCount($objectId);
        
        $reservesCount = [];
        foreach ($reserves as $r) {
            $reservesCount[] = $r->toArray([
                'object_uid',
                'status_uid',
                'codetype_uid',
                'created_at',
            ], [
                'object',
                'count',
                'codeType',
            ]);
        }
        
        $result = [
            'message'  => '',
            'reserves' => $reservesCount,
        ];
        
        foreach ($reservesCount as &$res) {
            if ($res['status_uid'] == GenerationStatus::STATUS_READY && $res['object_uid'] == $objectId && $res['codetype_uid'] != CodeType::CODE_TYPE_SN) {
                $result['message'] .= ' ' . $res['count'] . ' ' . $res['codeType']['name'];
            }
        }
        if (!empty($result['message'])) {
            $result['message'] = 'Зарезервированно' . $result['message'];
        } else {
            $result['message'] = 'Зарезервированно 0';
        }
        
        return $result;
    }
    
    /**
     * Резервация кода
     *
     * @throws MethodNotAllowedHttpException
     */
    public function actionCreate()
    {
        $params = \Yii::$app->getRequest()->getBodyParams();
        
        $model = new $this->modelClass([
            'scenario' => 'reserve',
        ]);
        $model->load($params, '');
        $model->is_rezerv = true;
        if ($model->save()) {
            $response = \Yii::$app->getResponse();
            $response->setStatusCode(201);
            $model->refresh();
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Создание заказа на резерв кодов", [["field" => "Параметры заказа", "value" => $params]]);
        
        return $model;
    }
    
    public function actionMagic()
    {
        $objectId = \Yii::$app->user->getIdentity()->object_uid;
        
        if (\Yii::$app->user->can('see-all-objects')) {
            $objectId = null;
        }
        
        $objectsArray = Facility::find()->andFilterWhere(['id' => $objectId]);
        $objects = [];
        foreach ($objectsArray->all() as $item) {
            $objects[$item->id] = $item->toArray();
        }
        
        $res = Generation::find();
        $res->andWhere(['is_rezerv' => true])
            ->andFilterWhere(['object_uid' => $objectId])
            ->orderBy(['created_at' => SORT_DESC])
            ->with('codeType');
        
        $response = [
            'reserves'    => [],
            'generations' => [],
        ];
        
        /** @var Generation $item */
        foreach ($res->all() as $item) {
            if (!in_array($item->codetype_uid, [CodeType::CODE_TYPE_INDIVIDUAL, CodeType::CODE_TYPE_GROUP])) {
                continue;
            }
            
            if ($item->status_uid == GenerationStatus::STATUS_READY) {
                $key = $item->object_uid . 'i' . $item->codetype_uid;
                
                if (!isset($response['reserves'][$key])) {
                    $response['reserves'][$key] = [
                        'object_uid' => $item->object_uid,
                        'object'     => (isset($objects[$item->object_uid])) ? $objects[$item->object_uid] : null,
                        'name'       => $item->codeType->name,
                        'count'      => 0,
                    ];
                }
                
                $response['reserves'][$key]['count'] += $item->cnt;
            }
            
            $json = $item->toArray([], ['codeType']);
            $json['object'] = (isset($objects[$json['object_uid']])) ? $objects[$json['object_uid']] : null;
            
            $response['generations'][] = $json;
        }
        
        $response['reserves'] = array_values($response['reserves']);
        
        return $response;
    }
    
    public function actionHistory()
    {
        $this->serializer = [
            'class'              => 'app\modules\itrack\components\boxy\Serializer',
            'collectionEnvelope' => 'reserves',
        ];
        
        $objectId = \Yii::$app->user->getIdentity()->object_uid;
        
        if (\Yii::$app->user->can('see-all-objects')) {
            $objectId = null;
        }
        
        $params = \Yii::$app->request->getQueryParams();
        if ($objectId) {
            $params['object_uid'] = $objectId;
        }
        
        $sort = new GenerationSort();
        $dataProvider = $sort->searchReserve($params);
        $dataProvider->query->with('codeType');
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_GENERATION, "Просмотр списка резерва кодов", []);
        return $dataProvider;
    }
}