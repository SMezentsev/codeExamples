<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 4:30
 */

namespace app\modules\itrack\controllers;


use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\SnGroups;
use yii\data\ActiveDataProvider;

/**
 * @OA\Get(
 *  path="/codeTypes",
 *  tags={"Типы кодов"},
 *  description="Справочник типов кодов",
 *  @OA\Response(
 *      response="200",
 *      description="Типы кодов",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="codeTypes",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Code_Type")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class CodeTypeController extends ActiveController
{
    
    use ControllerTrait;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'codeTypes',
    ];
    
    public $modelClass = 'app\modules\itrack\models\CodeType';
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['delete'], $actions['create'], $actions['update']);
        
        return $actions;
    }
    
    /**
     * @url codeTypes/snGroups
     *
     * @return ActiveDataProvider
     */
    
    public function prepareDataProvider()
    {
        $model = $this->modelClass;
        $dataProvider = new ActiveDataProvider([
            'query' => $model::find()->andWhere('id != 3'),
        ]);
        
        return $dataProvider;
    }
}