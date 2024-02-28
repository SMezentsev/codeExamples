<?php

namespace app\modules\itrack\models\traits;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * Trait CreateModelFromArray
 */
trait CreateModelFromArray
{
    /**
     * @param array $properties
     *
     * @return ActiveRecord
     */
    public static function createFromArray(array $properties)
    {
        /** @var ActiveRecord $model */
        $model = new self();
        $model->setAttributes($properties);
        $model->save();
        
        return $model;
    }
}