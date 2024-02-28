<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: Jana
 * Date: 17.04.2015
 * Time: 7:32
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\web\NotFoundHttpException;

class CheckController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass     = 'app\modules\itrack\models\Check';
    public $modelCodeClass = 'app\modules\itrack\models\Code';
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'checks',
    ];
    
    public function actionCode($code)
    {
        $modelClass = $this->modelCodeClass;
        $model = $modelClass::findOne(['code' => $code]);
        
        if (!$model) {
            throw new NotFoundHttpException("Code not found: {$code}");
        }
        
        return $model;
    }
}