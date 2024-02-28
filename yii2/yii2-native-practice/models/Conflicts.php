<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use Yii;

class Conflicts extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'conflicts';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['typeof', 'oldvalue', 'newvalue'], 'string'],
            [['created_at'], 'safe'],
        ];
    }
    
    public function fields()
    {
        return [
            'uid'        => 'id',
            'typeof',
            'oldvalue',
            'newvalue',
            'object_uid',
            'is_processed',
            'created_at' => function () {
                return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
        ];
    }
    
    public function extraFields()
    {
        return [
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'       => 'ID',
            'typeof'   => '��� ���������',
            'oldvalue' => '������ ��������',
            'newvalue' => '����� ��������',
        ];
    }
}
