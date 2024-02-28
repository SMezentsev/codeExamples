<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 19.10.15
 * Time: 11:07
 */

namespace app\modules\itrack\components\boxy\behavior;


class iRedis extends \yii\base\Behavior
{
    /**
     * Получение hash данных в виде массива или объекта
     *
     * @param string                $key
     * @param string|array|callable $class the object type.
     *
     * @return array|Facility|false
     * @throws \yii\base\InvalidConfigException
     */
    public function getHash($key, $class = null)
    {
        try {
            $data = $this->owner->hgetall($key);
        } catch (\Exception $e) {
            return false;
        }
        $row = [];
        $c = count($data);
        for ($i = 0; $i < $c;) {
            $row[$data[$i++]] = $data[$i++];
        }
        
        if (null === $class) {
            return $row;
        } else {
            $object = \Yii::createObject($class);
            foreach ($row as $key => $value) {
                if ($object->hasProperty($key)) {
                    $object->$key = $value;
                }
            }
            
            return $object;
        }
    }
}