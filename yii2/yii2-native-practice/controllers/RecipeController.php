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

class RecipeController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Recipe';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'Recipes',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        //unset($actions['delete']);
        return $actions;
    }
    
    public function prepareDataProvider()
    {
        $model = $this->modelClass;
        $params = \Yii::$app->request->getQueryParams();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $model::find(),
            'pagination' => false,
        ]);
        
        return $dataProvider;
    }
    
}
