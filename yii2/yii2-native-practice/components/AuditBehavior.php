<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\modules\itrack\components;

use yii\db\BaseActiveRecord;

/**
 * Description of AuditBehavior
 *
 * @author Jana
 */
class AuditBehavior extends \yii\base\Behavior
{
    private static $_oldAttributes;
    private static $_newAttributes;
    
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_FIND    => 'afterFind',
            BaseActiveRecord::EVENT_AFTER_INSERT  => 'afterInsert',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            BaseActiveRecord::EVENT_AFTER_UPDATE  => 'afterUpdate',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }
    
    public function afterFind()
    {
    }
    
    public function afterInsert()
    {
        $this->setNewAttributes($this->owner->attributes);
        $newAttributes = $this->getNewAttributes();
        
        $op = \app\modules\itrack\models\AuditOperation::getOperationByClassName($this->owner->className());
        $cn = $this->owner->className();
        $el = new $cn;
        $labels = $el->attributeLabels();
        $data = array_map(
            function ($a, $c) use ($labels) {
                if (isset($labels[$c])) {
                    $c = $labels[$c] . " ($c)";
                }
                
                return ['field' => $c, 'value' => $a];
            }, $newAttributes, array_keys($newAttributes));
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                unset($data[$k]);
            }
        }
        \app\modules\itrack\models\AuditLog::Audit($op, 'Добавление', $data);
        $this->normalizeNewAttributes();
    }
    
    public function beforeUpdate()
    {
        $this->normalizeOldAttributes();
        $this->setOldAttributes($this->owner->getOldAttributes());
    }
    
    public function afterUpdate()
    {
        $oldAttributes = $this->getOldAttributes();
        $this->setNewAttributes($this->owner->attributes);
        $newAttributes = $this->getNewAttributes();
        
        $op = \app\modules\itrack\models\AuditOperation::getOperationByClassName($this->owner->className());
        $cn = $this->owner->className();
        $el = new $cn;
        $labels = $el->attributeLabels();
        $data = array_map(
            function ($a, $b, $c) use ($labels) {
                if (isset($labels[$c])) {
                    $c = $labels[$c] . " ($c)";
                }
                if ($a != $b) {
                    return ['field' => $c, 'value' => $a, 'new' => $b];
                } else {
                    return null;
                }
            }, $oldAttributes, $newAttributes, array_keys($newAttributes));
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                unset($data[$k]);
            }
        }
        \app\modules\itrack\models\AuditLog::Audit($op, 'Изменение', $data);
        
        $this->normalizeNewAttributes();
        $this->normalizeOldAttributes();
    }
    
    public function beforeDelete()
    {
        $this->setOldAttributes($this->owner->attributes);
        $oldAttributes = $this->getOldAttributes();
        
        $op = \app\modules\itrack\models\AuditOperation::getOperationByClassName($this->owner->className());
        $cn = $this->owner->className();
        $el = new $cn;
        $labels = $el->attributeLabels();
        $data = array_map(
            function ($a, $c) use ($labels) {
                if (isset($labels[$c])) {
                    $c = $labels[$c] . " ($c)";
                }
                
                return ['field' => $c, 'value' => $a];
            }, $oldAttributes, array_keys($oldAttributes));
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                unset($data[$k]);
            }
        }
        \app\modules\itrack\models\AuditLog::Audit($op, 'Удаление', $data);
        
        $this->normalizeOldAttributes();
    }
    
    public function getNewAttributes()
    {
        return self::$_newAttributes;
    }
    
    public function setNewAttributes($attributes)
    {
        self::$_newAttributes = $attributes;
    }
    
    public function normalizeNewAttributes()
    {
        self::$_newAttributes = null;
    }
    
    public function getOldAttributes()
    {
        return self::$_oldAttributes;
    }
    
    public function setOldAttributes($attributes)
    {
        self::$_oldAttributes = $attributes;
    }
    
    public function normalizeOldAttributes()
    {
        self::$_oldAttributes = null;
    }
    
}
