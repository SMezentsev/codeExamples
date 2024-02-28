<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 6:55
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\HistoryOperation;

/**
 * @OA\Get(
 *  path="/historyOperations",
 *  tags={"Типы операций"},
 *  description="Получение типов операций",
 *  @OA\Response(
 *      response="200",
 *      description="Типы операций",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="historyOperations",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_HistoryOperation")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class HistoryOperationController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = HistoryOperation::class;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'historyOperations',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['delete'], $actions['create'], $actions['update']);
        
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $model = $this->modelClass;
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $model::find(),
        ]);
        
        return $dataProvider;
    }
}