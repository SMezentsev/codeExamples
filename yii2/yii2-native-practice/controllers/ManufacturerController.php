<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 19.01.16
 * Time: 14:51
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Manufacturer;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\NotAcceptableHttpException;

/**
 * @OA\Post(
 *   path="/rf/manufacturers",
 *   tags={"Производители"},
 *   description="Создание производителя",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Производитель",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/rf/manufacturers",
 *  tags={"Производители"},
 *  description="Получение списка производителей",
 *  @OA\Response(
 *      response="200",
 *      description="Производители",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="manufacturers",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/rf/manufacturers/{id}",
 *  tags={"Производители"},
 *  description="Получение производителя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Производитель",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/rf/manufacturers/{id}",
 *  tags={"Производители"},
 *  description="Изменение производителя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Производитель",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Manufacturer")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/rf/manufacturers/{id}",
 *  tags={"Производители"},
 *  description="Удаление производителя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="204",
 *      description="",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class ManufacturerController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = Manufacturer::class;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'manufacturers',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        if (SERVER_RULE != SERVER_RULE_RF) {
            unset($actions['update']);
            unset($actions['delete']);
        }
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $modelClass = $this->modelClass;
        
        /** @var ActiveQuery $query */
        $query = $modelClass::find();
        $query->andFilterWhere(['ilike', 'name', \Yii::$app->request->get('name')])->with('nomenclatures');
        if (\Yii::$app->request->getQueryParam('external') != 'all') {
            $query->andWhere(['external' => false]);
        }
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['manufacturers' => array_map(function ($v) {
                return ["id" => $v["id"], "uid" => $v["id"], "inn" => $v["inn"], "kpp" => $v["kpp"], "name" => $v["name"] . ($v["external"] ? " - сторонний" : ""), "fnsid" => $v["fnsid"], "ownerid" => $v["ownerid"]];
            }, $query->orderBy('name')->all())];
        }
        
        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
            case 'delete':
                if (!\Yii::$app->user->can('reference-manufacturers-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-manufacturers')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-manufacturers') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}