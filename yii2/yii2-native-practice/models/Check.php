<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use Yii;

/**
 * Class Check
 *      Проверка кода потребителями, фиксируются все проверки потребителями- фиксируется данные переданыне оптребителем и ответно сообщение возвращенное потребителю
 *
 *
 * @property integer $id           - Идентификатор
 * @property integer $source_uid   - Источник проверки - ССылка - SMS, МП, КЦ, Сайт...
 * @property string  $created_at   - даа создания
 * @property string  $txt          - ответное сообщение
 * @property integer $code_uid     - Ссылка на код
 * @property integer $ucnt         - Порядковый номер проверки данного кода
 * @property string  $lon
 * @property string  $address
 * @property string  $device
 * @property string  $answer
 *
 * Методы:
 *  -просмотр, создание
 *  отчеты
 */
class Check extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'checks';
    }
    
    public function fields()
    {
        return [
            'uid'        => 'id',
            'source_uid',
            'created_at' => function () {
                return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
            'code_uid',
            'ucnt',
            'txt',
        ];
    }
    
    public function extraFields()
    {
        return [
            'code',
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['source_uid', 'ucnt'], 'required'],
            [['source_uid', 'code_uid', 'ucnt'], 'integer'],
            [['created_at'], 'safe'],
            [['txt', 'lat', 'lon', 'address', 'device', 'answer'], 'string'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'source_uid' => 'ID источника проверки',
            'created_at' => 'дата проверки',
            'txt'        => 'проверяемый текст',
            'code_uid'   => 'ID кода, если проверялся код',
            'ucnt'       => 'Номер проверки кода (codeid is not null)',
            'lat'        => 'Lat',
            'lon'        => 'Lon',
            'address'    => 'Адрес проверки',
            'device'     => 'Устройство',
            'answer'     => 'Ответ на проверку',
        ];
    }

//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getSource()
//    {
//        return $this->hasOne(CheckSource::class, ['id' => 'source_uid']);
//    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCode()
    {
        return $this->hasOne(Code::class, ['id' => 'code_uid']);
    }
}