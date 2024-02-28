<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 16.04.2015
 * Time: 6:18
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\Facility;
use app\modules\itrack\models\FacilitySort;
use yii\data\ActiveDataProvider;
use yii\web\NotAcceptableHttpException;
use yii\web\ServerErrorHttpException;

/**
 * @OA\Post(
 *   path="/objects",
 *   tags={"Объекты"},
 *   description="Создание объекта",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Объект",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/objects",
 *  tags={"Объекты"},
 *  description="Получение списка объектов",
 *  @OA\Response(
 *      response="200",
 *      description="Объекты",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="objects",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/objects/{id}",
 *  tags={"Объекты"},
 *  description="Получение объекта",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Объект",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/objects/{id}",
 *  tags={"Объекты"},
 *  description="Изменение объекта",
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
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Объект",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Facility")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/objects/{id}",
 *  tags={"Объекты"},
 *  description="Удаление объекта",
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
class ObjectController extends ActiveController
{
    
    use ControllerTrait;
    public $modelClass = Facility::class;
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'objects',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['delete']);
            unset($actions['update']);
        }
        
        return $actions;
    }
    
    public function actionUsers($objectID)
    {
        /** @var \app\modules\itrack\models\Facility $object */
        $object = $this->findModel($objectID);
        
        $this->serializer['collectionEnvelope'] = 'users';
        
        return new ActiveDataProvider([
            'query' => $object->getUsers(),
        ]);
    }
    
    public function prepareDataProvider()
    {
        $objectSort = new FacilitySort();
        $dataProvider = $objectSort->search(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects') && !\Yii::$app->user->can('reference-objects') && \Yii::$app->request->getQueryParam('combo') != 'true') {
            $dataProvider->query->andWhere(['=', 'id', \Yii::$app->user->identity->object_uid]);
        }
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['objects' => array_map(function ($v) {
                return ["uid" => $v["id"], "id" => $v["id"], "name" => $v["name"], "guid" => $v["guid"], "fnsid" => $v["fns_subject_id"], "parent_uid" => $v["parent_uid"]];
            }, $dataProvider->query->orderBy('name')->all())];
        }
        
        return $dataProvider;
    }
    
    public function actionDelete($id)
    {
        /** @var Object $model */
        $model = $this->findModel($id);
        if (!$model->canDelete) {
            $model->addError('uid', 'Невозможно удалить данный объект');
            
            return $model;
        }
        
        if ($model->delete() === false) {
            throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
        }
        
        \Yii::$app->getResponse()->setStatusCode(204);
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
                if (!\Yii::$app->user->can('reference-objects-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete':
                if (!$model->canDelete) {
                    throw new NotAcceptableHttpException("Запрет на удаление");
                }
                if (!\Yii::$app->user->can('reference-objects-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-objects')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-objects') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}