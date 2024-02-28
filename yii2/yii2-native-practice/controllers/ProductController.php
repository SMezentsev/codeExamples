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
use app\modules\itrack\models\Product;
use app\modules\itrack\models\ProductSort;
use yii\base\ActionEvent;
use yii\web\NotAcceptableHttpException;

/**
 * @OA\Post(
 *   path="/products",
 *   tags={"Товарные карты"},
 *   description="Создание товарной карты",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Product")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Товарная карта",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Product")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/products",
 *  tags={"Товарные карты"},
 *  description="Получение списка товарных карт",
 *  @OA\Response(
 *      response="200",
 *      description="Товарные карты",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="products",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Product")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/products/{id}",
 *  tags={"Товарные карты"},
 *  description="Получение товарной карты",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Товарная карта",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Product")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/products/{id}",
 *  tags={"Товарные карты"},
 *  description="Изменение товарной карты",
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
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Product")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Товарная карта",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Product")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/products/{id}",
 *  tags={"Товарные карты"},
 *  description="Удаление товарной карты",
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
class ProductController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = Product::class;
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'products',
    ];
    
    public function authExcept()
    {
        return ['update-external'];
    }
    
    public function init()
    {
        $this->on(self::EVENT_BEFORE_ACTION, function ($event) {
            /** @var ActionEvent $event */
            if ($event->action && $event->action->id == 'create') {
                $params = \Yii::$app->getRequest()->getBodyParams();
                if (!isset(\Yii::$app->user->identity->object_uid) && \Yii::$app->user->identity->object_uid > 0) {
                    $params['object_uid'] = \Yii::$app->user->identity->object_uid;
                }
                
                if (isset($params['components'])) {
                    $series = $cdate = $expdate = [];
                    $series_one = "";
                    foreach ($params['components'] as $component) {
                        if (empty($series_one) && !empty($component['series'])) {
                            $series_one = $component['series'];
                        }
                        if (isset($component['series'])) {
                            $series[] = $component['series'];
                        }
                        if (isset($component['cdate'])) {
                            $cdate[] = $component['cdate'];
                        }
                        if (isset($component['expdate'])) {
                            $expdate[] = $component['expdate'];
                        }
                    }
                    
                    if (count($expdate)) {
                        $fdate = new \DateTime($expdate[0]);
                        foreach ($expdate as $expd) {
                            $expd = new \DateTime($expd);
                            if ($expd < $fdate) {
                                $fdate = $expd;
                            }
                        }
                    }
                    
                    $format = "m Y";
                    if (isset($params["format"]) && $params["format"] == "YYYY-MM-DD") {
                        $format = "d m Y";
                    }
                    $cdate = array_map(function ($item) use ($format) {
                        return (new \DateTime($item))->format($format);
                    }, $cdate);
                    
                    $params['series'] = $series_one;
                    $params['cdate'] = implode(' ', $cdate);
                    $params['expdate'] = $fdate->format($format);
                    $params['expdate_full'] = $fdate->format("d m Y");
                    
                    \Yii::$app->getRequest()->setBodyParams($params);
                }
            }
        });
    }
    
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
    
    /**
     * Обновление товарной карты и номенклатры от внешних систем
     * вход:
     * POST DATA
     * {
     *  gtin,productCode,serie,name,manufacturer,ean13,productionDate
     * }
     *
     * @return type
     */
    public function actionUpdateExternal()
    {
        $params = \Yii::$app->request->getBodyParams();
        
        return Product::updateExternal($params);
    }
    
    /**
     * CRUD index
     *
     * @return type
     */
    public function prepareDataProvider()
    {
        $sort = new ProductSort();
        $dataProvider = $sort->search(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'product.object_uid', \Yii::$app->user->identity->object_uid]);
        }
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['products' => array_map(function ($v) {
                return ["uid" => $v["id"], 'object_uid' => $v["object_uid"], "id" => $v["id"], "name" => $v->nomenclature["name"] . " (" . $v["series"] . ")"];
            }, $dataProvider->query->andWhere(['accepted' => true])->orderBy('nomenclature.name')->all())];
        }
        
        return $dataProvider;
    }
    
    /**
     * @param string  $action
     * @param Product $model
     * @param array   $params
     *
     * @throws NotAcceptableHttpException
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
                if (!\Yii::$app->user->can('reference-product-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete':
                if (!$model->canDelete) {
                    throw new NotAcceptableHttpException("Запрет на удаление");
                }
                if (!\Yii::$app->user->can('reference-product-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-product')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-product') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
}