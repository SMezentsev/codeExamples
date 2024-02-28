<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 5:33
 */

namespace app\modules\itrack\controllers;


use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\data\ActiveDataProvider;

/**
 * @OA\Get(
 *  path="/generationStatuses",
 *  tags={"Статусы заказа кодов"},
 *  description="Статусы заказа кодов",
 *  @OA\Response(
 *      response="200",
 *      description="Статусы заказа кодов",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="generationStatuses",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Generation_Status")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class GenerationStatusController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\GenerationStatus';
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'generationStatuses',
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
        $dataProvider = new ActiveDataProvider([
            'query' => $model::find()->orderBy('name'),
        ]);
        
        return $dataProvider;
    }
    
}