<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%good_category}}".
 *
 * @property string $name
 */
class GoodCategory extends ActiveRecord
{
    public const GOOD_TYRE = 1;
    public const GOOD_DISK = 2;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%good_category}}';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'eng_name'], 'string'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => 'Наименование',
        ];
    }
    
}
