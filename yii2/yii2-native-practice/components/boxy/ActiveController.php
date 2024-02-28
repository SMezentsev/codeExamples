<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 16.04.15
 * Time: 16:49
 */

namespace app\modules\itrack\components\boxy;

class ActiveController extends \yii\rest\ActiveController
{
    
    public function actions()
    {
        $actions = parent::actions();
        if (isset($actions['create'])) {
            $actions['create']['class'] = 'app\modules\itrack\components\boxy\CreateAction';
        }
        
        return $actions;
    }
    
}