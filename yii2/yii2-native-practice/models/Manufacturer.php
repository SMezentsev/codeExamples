<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * This is the model class for table "manufacturer".
 *
 * @property integer                   $id
 * @property string                    $name
 * @property string                    $fnsid
 * @property string                    $ownerid
 * @property string                    $inn
 * @property string                    $kpp
 * @property string                    $address
 * @property boolean                   $external
 *
 * @property Nomenclature[]|array|null $nomenclatures
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Manufacturer",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="string", example="1"),
 *          @OA\Property(property="name", type="string", example="Тестовый производитель"),
 *          @OA\Property(property="fnsid", type="string", example=null),
 *          @OA\Property(property="inn", type="string", example=null),
 *          @OA\Property(property="kpp", type="string", example=null),
 *          @OA\Property(property="ownerid", type="string", example="12313112-1313-1232-1321-312312312312"),
 *          @OA\Property(property="address", type="string", example=null),
 *          @OA\Property(property="md", type="array", @OA\Items(ref="#/components/schemas/app_modules_itrack_models_ManufacturerMd")),
 *      }
 * )
 */
class Manufacturer extends ActiveRecord
{
    static $auditOperation = AuditOperation::OP_MANUF;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'manufacturer';
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
            [['name', 'fnsid', 'ownerid', 'inn', 'kpp', 'address'], 'string'],
            [['external'], 'boolean'],
            [['name'], 'required'],
            [['name'], 'unique', 'message' => 'Контрагент с таким наименованием уже создан'],
            [['name', 'fnsid'], 'required', 'on' => 'odinStask', 'message' => 'Поле {attribute} - является обязательным'],
            ['name', 'unique', 'targetAttribute' => ['name', 'external']],
        ];
    }
    
    public function scenarios()
    {
        return array_merge(parent::scenarios(), [
            'odinStask' => ['name', 'fnsid'],
        ]);
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'      => 'ID',
            'name'    => 'Название',
            'fnsid'   => 'Идентификатор',
            'ownerid' => 'Регистрационный номер',
        ];
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
            'fnsid',
            'inn',
            'kpp',
            'ownerid',
            'address',
            'md',
        ];
    }
    
    public function extraFields()
    {
        return [
            'canDelete',
        ];
    }
    
    public function getCanDelete()
    {
        if (count($this->nomenclatures)) {
            return false;
        }
        foreach ($this->nomenclatures as $item) {
            if ($item->canDelete == false) {
                return false;
            }
        }
        
        return true;
    }
    
    public function getNomenclatures()
    {
        return $this->hasMany(Nomenclature::class, ['manufacturer_uid' => 'id']);
    }
    
    public function getMd()
    {
        return $this->hasMany(ManufacturerMd::class, ['manufacturer_uid' => 'id']);
    }
}
