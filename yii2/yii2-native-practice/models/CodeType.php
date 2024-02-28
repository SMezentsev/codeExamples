<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "code_types".
 *
 * @property integer      $id
 * @property string       $name
 *
 * @property Generation[] $generations
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Code_Type",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example=1),
 *          @OA\Property(property="name", type="string", example="Индивидуальный"),
 *      }
 * )
 */
class CodeType extends ActiveRecord
{
    const CODE_TYPE_INDIVIDUAL = 1;
    const CODE_TYPE_GROUP = 2;
    const CODE_TYPE_SN = 3;
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'code_types';
    }

    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }

    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
        ];
    }
    
    public function extraFields()
    {
        return [
            'generations',
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'   => 'ID',
            'name' => 'наименование типа кода',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
//    public function getCodeHierarchies()
//    {
//        return $this->hasMany(CodeHierarchy::class, ['codetypeid' => 'id']);
//    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGenerations()
    {
        return $this->hasMany(Generation::class, ['codetype_uid' => 'id']);
    }
}