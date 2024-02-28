<?php

namespace app\modules\itrack\models;

/**
 * This is the model class for table "manufacturer_md".
 *
 * @property int          $id
 * @property int          $manufacturer_uid
 * @property string       $name
 * @property string       $fns_subject_id
 *
 * @property Manufacturer $manufacturerU
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_ManufacturerMd",
 *      type="object",
 *      properties={
 *          @OA\Property(property="id", type="string", example="1"),
 *          @OA\Property(property="manufacturer_uid", type="integer", example="2"),
 *          @OA\Property(property="name", type="string", example="Тест"),
 *          @OA\Property(property="fns_subject_id", type="string", example=null),
 *      }
 * )
 */
class ManufacturerMd extends \yii\db\ActiveRecord
{
    static $auditOperation = \app\modules\itrack\models\AuditOperation::OP_MANUF;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'manufacturer_md';
    }
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['manufacturer_uid', 'name', 'fns_subject_id'], 'required'],
            [['manufacturer_uid'], 'default', 'value' => null],
            [['manufacturer_uid'], 'integer'],
            [['name', 'fns_subject_id'], 'string'],
            [['manufacturer_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Manufacturer::class, 'targetAttribute' => ['manufacturer_uid' => 'id']],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'               => 'ID',
            'manufacturer_uid' => 'Manufacturer Uid',
            'name'             => 'Name',
            'fns_subject_id'   => 'Fns Subject ID',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getManufacturer()
    {
        return $this->hasOne(Manufacturer::class, ['id' => 'manufacturer_uid']);
    }
}
