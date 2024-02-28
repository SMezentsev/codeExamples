<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "messages".
 *
 * @property integer $id
 * @property string  $name
 * @property string  $note
 * @property string  $message
 */
class Message extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'messages';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'note', 'message'], 'string'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'      => 'ID',
            'name'    => 'наименование сообщения ENG',
            'note'    => 'Note',
            'message' => 'Message',
        ];
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
            'note',
            'message',
        ];
    }
}
