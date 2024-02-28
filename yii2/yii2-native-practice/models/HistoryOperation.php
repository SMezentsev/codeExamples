<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "history_operation".
 *
 * @property integer   $id
 * @property string    $name
 * @property boolean   $active
 *
 * @property History[] $histories
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_HistoryOperation",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example="1"),
 *          @OA\Property(property="name", type="string", example="Добавление"),
 *          @OA\Property(property="active", type="boolean", example=true),
 *      }
 * )
 */
class HistoryOperation extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'history_operation';
    }
    
    static function find()
    {
        $query = parent::find();
        $query->andWhere(['active' => true]);
        
        return $query;
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
            [['active'], 'boolean'],
        ];
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
            'active',
        ];
    }
    
    public function extraFields()
    {
        return [
            'histories',
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'   => 'ID',
            'name' => 'наименование операции',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHistories()
    {
        return $this->hasMany(History::class, ['operation_uid' => 'id']);
    }
}