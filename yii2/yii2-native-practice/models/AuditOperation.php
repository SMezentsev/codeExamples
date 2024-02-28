<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "audit_operations".
 *
 * @property integer $id
 * @property string  $name_rus
 * @property string  $name_eng
 *
 */
class AuditOperation extends ActiveRecord
{
    
    const OP_AUTH = 1;
    const OP_LOGOUT = 2;
    const OP_APKVERSION = 3;
    const OP_REPORT_FILE = 4;
    const OP_REPORT = 5;
    const OP_REPORT_DATA = 6;
    const OP_CODES = 7;
    const OP_DESTRUCTOR = 8;
    const OP_DEFAULT = 9;
    const OP_EQUIP = 10;
    const OP_MANUF = 11;
    const OP_NOMENCLATURE = 12;
    const OP_OBJECT = 13;
    const OP_PRODUCT = 14;
    const OP_USER = 15;
    const OP_FNS = 16;
    const OP_GENERATION = 17;
    const OP_INVOICE = 18;
    const OP_FNSNOTIFY = 19;
    const OP_CONFIG = 20;
    const OP_CONNECTORS = 21;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'audit_operations';
    }
    
    static function getOperationByClassName($cn)
    {
        if (isset($cn::$auditOperation)) {
            return $cn::$auditOperation;
        }
        
        return self::OP_DEFAULT;
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name_rus', 'name_eng'], 'string'],
        ];
    }
    
}
