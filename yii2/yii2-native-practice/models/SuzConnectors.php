<?php

namespace app\modules\itrack\models;

use Yii;

/**
 * This is the model class for table "suz_connectors".
 *
 * @property int    $id
 * @property string $name
 * @property string $url
 * @property string $client_token
 * @property string $omsid
 * @property int    $templateid
 * @property bool   $freecode
 * @property int    $paymenttype
 */
class SuzConnectors extends \yii\db\ActiveRecord
{
    static $auditOperation = \app\modules\itrack\models\AuditOperation::OP_CONNECTORS;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'suz_connectors';
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
            [['name', 'url', 'client_token', 'omsid'], 'required'],
            [['name', 'url', 'client_token', 'omsid'], 'string'],
            [['name'], 'unique', 'message' => 'Подключение к СУЗ с таким наименованием уже создано'],
            [['templateid', 'paymenttype'], 'default', 'value' => null],
            [['templateid', 'paymenttype'], 'integer'],
            [['freecode'], 'boolean'],
        ];
    }
    
    public function fields()
    {
        return array_merge(parent::fields(), [
            'uid' => 'id',
        ]);
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'           => Yii::t('app', 'ID'),
            'name'         => Yii::t('app', 'Наименование подключения'),
            'url'          => Yii::t('app', 'Url адрес сервиса СУЗ'),
            'client_token' => Yii::t('app', 'Токен клиента'),
            'omsid'        => Yii::t('app', 'Oms ID'),
            'templateid'   => Yii::t('app', 'Идентификатор шаблона КМ'),
            'freecode'     => Yii::t('app', 'Признак оплаты эмиссии КМ'),
            'paymenttype'  => Yii::t('app', 'Тип оплаты'),
        ];
    }
}
