<?php

namespace app\modules\itrack\models;

use app\modules\itrack\models\traits\CreateModelFromArray;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%ism_log}}".
 *
 * @property int         $id
 * @property int|null    $operation_id
 * @property string|null $body
 * @property string|null $created
 * @property string|null $updated
 * @property string|null $log_type
 */
class IsmLog extends ActiveRecord
{
    use CreateModelFromArray;
    
    const ISM_LOG_TYPE_SENDDOC = 'sendDoc';
    const ISM_LOG_TYPE_CHECK_DOC_STATUS = 'checkDocStatus';
    const ISM_LOG_TYPE_RESPONSE_DOC = 'responseDoc';
    const ISM_LOG_TYPE_GET_OK = 'getOk';
    const ISM_LOG_TYPE_GET_ERR = 'getErr';
    const ISM_LOG_TYPE_GET_PARTED = 'getParted';
    
    protected static $ismLogTypeNames = [
        'unknown'        => 'Неизвестное действие',
        'sendDoc'        => 'Отправка документа',
        'checkDocStatus' => 'Проверка статуса обработки документа',
        'responseDoc'    => 'Получение квитанции',
        'getOk'          => 'Документ принят',
        'getErr'         => 'Документ отклонен',
        'getParted'      => 'Документ принят частично',
    ];
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ism_log';
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['operation_id'], 'default', 'value' => null],
            [['operation_id'], 'integer'],
            [['body'], 'string'],
            [['created', 'updated'], 'safe'],
            [['log_type'], 'string', 'max' => 255],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'           => Yii::t('app', 'ID'),
            'operation_id' => Yii::t('app', 'Operation ID'),
            'body'         => Yii::t('app', 'Body'),
            'created'      => Yii::t('app', 'Created'),
            'updated'      => Yii::t('app', 'Updated'),
            'log_type'     => Yii::t('app', 'Log Type'),
        ];
    }
    
    /**
     * @param $logTypeId
     *
     * @return string
     */
    public static function getLogType($logTypeId)
    {
        return self::$ismLogTypeNames[$logTypeId] ?? self::$ismLogTypeNames['unknown'];
    }
}
