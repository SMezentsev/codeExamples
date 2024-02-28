<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 15.04.15
 * Time: 12:40
 */

namespace app\modules\itrack\components\boxy;


use yii\base\Arrayable;
use yii\helpers\ArrayHelper;

class Serializer extends \yii\rest\Serializer
{
    public $afterSerializeModels;

//    /**
//     * Serializes a set of models.
//     * @param array $models
//     * @return array the array representation of the models
//     */
//    protected function serializeModels(array $models)
//    {
////        var_dump($models); die;
//        $result = parent::serializeModels($models);
////        var_dump($result); die;
//
////        if ($this->afterSerializeModels) {
////            return call_user_func($this->afterSerializeModels, $result);
////        }
//
//        return $models;
//    }
    
    /**
     * Serializes a set of models.
     *
     * @param array $models
     *
     * @return array the array representation of the models
     */
    protected function serializeModels(array $models)
    {
        list ($fields, $expand) = $this->getRequestedFields();
        foreach ($models as $i => $model) {
            if ($model instanceof Arrayable) {
                $models[$i] = $model->toArray($fields, $expand);
            } elseif (is_array($model)) {
                $models[$i] = ArrayHelper::toArray($model);
            }
        }
        
        if ($this->afterSerializeModels) {
            return call_user_func($this->afterSerializeModels, $models);
        }

//        var_dump($models); die;
        return $models;
    }
}