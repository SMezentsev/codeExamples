<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "constants".
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 */
class Constant extends ActiveRecord
{
    
    static $data           = [];
    static $auditOperation = \app\modules\itrack\models\AuditOperation::OP_CONFIG;
    
    public static function tableName()
    {
        return 'constants';
    }
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    static function get($name)
    {
        if (isset(self::$data[$name])) {
            return self::$data[$name];
        }
        $val = self::find()
                ->andWhere(['name' => $name])
                ->one()["value"] ?? '';
        self::$data[$name] = $val;
        
        return $val;
    }
    
    public function rules()
    {
        return [
            [['name', 'value', 'description'], 'safe'],
            [['name', 'value', 'description'], 'string'],
            [['name', 'value'], 'required'],
        ];
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
}
