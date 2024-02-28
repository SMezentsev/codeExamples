<?php

namespace app\modules\itrack\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "{{%ism_doc}}".
 *
 * @property int         $id
 * @property string|null $document_id
 * @property string|null $request_id
 * @property int|null    $type
 * @property string|null $body
 * @property string|null $status
 * @property string|null $created
 * @property string|null $updated
 * @property int|null    $operation_id
 * @property string|null $callback_token
 * @property bool|null   $income
 * @property string|null $callback_type
 * @property int|null    $connection_id
 */
class IsmDoc extends ActiveRecord
{
    const STATUS_NEW = 'new';
    const STATUS_SENDED = 'sended';
    const STATUS_FAILED = 'failes';
    const STATUS_PROCESSED = 'processed';
    
    const ISM_STATUS_PROCESSED = 'PROCESSED_DOCUMENT';
    const ISM_STATUS_FAILED = 'FAILED';
    const ISM_STATUS_FAILED_READY = 'FAILED_RESULT_READY';
    const ISM_STATUS_PARTED = 'PARTED';
    
    const STATE_RESPONSE_ERROR = 9;
    const STATE_RESPONSE_SUCCESS = 8;
    const STATE_RESPONSE_PARTED = 7;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ism_doc';
    }
    
    static function getLast($connectionId)
    {
        return self::find()
            ->andWhere(['connection_id' => $connectionId, 'income' => true])
            ->select(['*', 'created' => new Expression("created::timestamp(0) - interval '1 hour'")])
            ->orderBy(new Expression('ism_doc.created desc nulls last'))
            ->limit(1);
    }
    
    /**
     * @param array $data
     *
     * @return self
     */
    public static function createFromArray($data)
    {
        $model = new self();
        $model->setAttributes($data);
        $model->save();
        
        return $model;
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'operation_id', 'connection_id'], 'default', 'value' => null],
            [['type', 'operation_id', 'connection_id'], 'integer'],
            [['body'], 'string'],
            [['created', 'updated'], 'safe'],
            [['income'], 'boolean'],
            [['document_id', 'request_id'], 'string', 'max' => 36],
            [['status', 'callback_token', 'callback_type'], 'string', 'max' => 255],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'             => 'ID',
            'document_id'    => 'ISM document ID',
            'request_id'     => 'Create Request ID',
            'type'           => 'Document Type',
            'body'           => 'Document Text',
            'created'        => 'Created',
            'updated'        => 'Updated',
            'operation_id'   => 'Operation ID',
            'callback_token' => 'Callback Token',
            'callback_type'  => 'Callback Type',
            'income'         => 'Income doc',
            'connection_id'  => 'connection id',
        ];
    }
    
    public static function getSendedDocuments($connectionId)
    {
        return self::find()
            ->select(['*', 'created' => new \yii\db\Expression("created::timestamp(0) - interval '3 day'")])
            ->where(['status' => IsmDoc::STATUS_SENDED, 'connection_id' => $connectionId])
            ->andWhere(['>=', 'ism_doc.created', new \yii\db\Expression('current_date - 30')])
            ->orderBy(['ism_doc.created' => SORT_ASC])
            ->all();;
    }
}
