<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

/**
 * This is the model class for table "check_source".
 *
 * @property integer $id
 * @property string  $name
 *
 * @property Check[] $checks
 */
class CheckSource extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'check_source';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string'],
        ];
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'   => 'ID',
            'name' => 'Name',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChecks()
    {
        return $this->hasMany(Check::class, ['source_uid' => 'id']);
    }
}