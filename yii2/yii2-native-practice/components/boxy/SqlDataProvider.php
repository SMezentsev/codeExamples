<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 12.01.16
 * Time: 0:18
 */

namespace app\modules\itrack\components\boxy;


class SqlDataProvider extends \yii\data\SqlDataProvider
{
    public $afterFind;
    
    public function getModels()
    {
        if ($this->afterFind instanceof \Closure) {
            return call_user_func($this->afterFind, parent::getModels());
        } else {
            return parent::getModels();
        }
    }
}