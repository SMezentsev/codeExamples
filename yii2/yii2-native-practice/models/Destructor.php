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
 * @property integer $id
 * @property string  $name
 * @property string  $inn
 * @property string  $kpp
 * @property string  $aoguid
 * @property string  $houseguid
 * @property string  $flat
 *
 */
class Destructor extends ActiveRecord
{
    
    static $auditOperation = AuditOperation::OP_DESTRUCTOR;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'destructor';
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
            'inn',
            'kpp',
            'aoguid',
            'houseguid',
            'flat',
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'uid'       => 'ID',
            'name'      => "Название",
            'inn'       => "ИНН",
            'kpp'       => "КПП",
            'aoguid'    => "Идентификатор ФИАС объекта",
            'houseguid' => "Идентификатор ФИАС дома",
            'flat'      => "Квартира",
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'inn', 'kpp', 'aoguid', 'houseguid', 'flat'], 'string'],
            [['name', 'inn', 'kpp'], 'required'],
            [['name'], 'unique', 'message' => 'Организация - Утилизация ЛП с таким наименованием уже создана'],
        ];
    }
}
