<?php

namespace app\modules\itrack\models\exchange\entities;

use Yii;
use app\modules\itrack\components\boxy\ActiveRecord;

class ExchangeLog extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'exchange_log';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['data', 'order_number'], 'required'],
            [['status', 'created_at', 'updated_at', 'last_error'], 'safe'],
            ['data', 'string']
        ];
    }

    public function fields() {
        return [
            'id',
            'data',
            'status',
            'order_number',
            'created_at',
            'updated_at',
        ];
    }
}