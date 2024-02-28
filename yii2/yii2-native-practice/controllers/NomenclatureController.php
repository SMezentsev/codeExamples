<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 16.04.2015
 * Time: 8:56
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\NomenclatureSort;
use yii\web\NotAcceptableHttpException;

/**
 * @OA\Post(
 *   path="/nomenclatures",
 *   tags={"Номенклатуры"},
 *   description="Создание номенклатуры",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Номенклатуры",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/nomenclatures",
 *  tags={"Номенклатуры"},
 *  description="Получение списка номенклатур",
 *  @OA\Response(
 *      response="200",
 *      description="Номенклатуры",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="nomenclatures",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/nomenclatures/{id}",
 *  tags={"Номенклатуры"},
 *  description="Получение номенклатуры",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Номенклатуры",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/nomenclatures/{id}",
 *  tags={"Номенклатуры"},
 *  description="Изменение номенклатуры",
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
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Номенклатуры",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/nomenclatures/{id}",
 *  tags={"Номенклатуры"},
 *  description="Удаление номенклатуры",
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
class NomenclatureController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = Nomenclature::class;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'nomenclatures',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

//        $actions['delete']['checkAccess'] = [$this, 'checkAccess'];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['update']);
            unset($actions['delete']);
            unset($actions['create']);
        }
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $sort = new NomenclatureSort();
        $dataProvider = $sort->search(\Yii::$app->request->getQueryParams());
        $dataProvider->query->with(['manufacturer', 'recipe']);
        $dataProvider->query->andWhere(['<>', 'code1c', '']);
        
        
        if (!\Yii::$app->user->can('see-all-objects') && (!isset(\Yii::$app->params["NomenclatureOnObject"]) || \Yii::$app->params["NomenclatureOnObject"] === true)) {
            $dataProvider->query->andWhere(new \yii\db\Expression('coalesce(object_uid,' . intval(\Yii::$app->user->identity->object_uid) . ') = ' . intval(\Yii::$app->user->identity->object_uid)));
//            $dataProvider->query->andWhere(['=','object_uid',]);
        }
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['nomenclatures' => array_map(function ($v) {
                return ["uid" => $v["id"], "id" => $v["id"], "name" => $v["name"], "code1c" => $v["code1c"]];
            }, $dataProvider->query->orderBy('code1c')->all())];
        }
        
        return $dataProvider;
    }
    
    /**
     * @param string       $action
     * @param Nomenclature $model
     * @param array        $params
     *
     * @throws NotAcceptableHttpException
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
                if (!\Yii::$app->user->can('reference-nomenclature-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete':
                if (!$model->canDelete) {
                    throw new NotAcceptableHttpException("Запрет на удаление");
                }
                if (!\Yii::$app->user->can('reference-nomenclature-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-nomenclature')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-nomenclature') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}