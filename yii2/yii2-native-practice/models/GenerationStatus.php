<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "generation_status".
 *
 * @property integer      $id
 * @property string       $name
 * @property string       $ru
 *
 * @property Generation[] $generations
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Generation_Status",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example=1),
 *          @OA\Property(property="name", type="string", example="Заявка создана"),
 *      }
 * )
 */
class GenerationStatus extends ActiveRecord
{
    
    const STATUS_CREATED = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_READY = 3;
    const STATUS_FAIL = 4;
    const STATUS_CLOSED = 5;
    const STATUS_NOTENOUGH = 6;
    const STATUS_CONFIRMED = 7;
    const STATUS_DECLINED = 8;
    const STATUS_TIMEOUT = 9;
    const STATUS_SKZKM = 10;
    const STATUS_CONFIRMEDWOADDON = 11;
    const STATUS_CONFIRMEDREPORT = 12;
    const STATUS_WAITING_CHECK_GTIN = 13;
    const STATUS_GENERATION_NOT_ACTIVE = 14;
    const STATUS_GTIN_CHECK_NOT_PASSED = 15;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'generation_status';
    }
    
    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'ru'], 'string'],
        ];
    }
    
    public function fields()
    {
        return [
            'uid'  => 'id',
            'name' => function () {
                return (!empty($this->ru)) ? $this->ru : $this->name;
            },
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
    public function attributeLabels()
    {
        return [
            'id'   => 'ID',
            'name' => 'наименование',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGenerations()
    {
        return $this->hasMany(Generation::class, ['status_uid' => 'id']);
    }
}