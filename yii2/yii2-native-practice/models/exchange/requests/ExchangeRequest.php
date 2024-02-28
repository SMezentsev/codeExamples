<?php

namespace app\modules\itrack\models\exchange\requests;

use app\modules\itrack\components\boxy\ModelHelperTrait;
use app\modules\itrack\models\exchange\exceptions\SerializeException;
use app\modules\itrack\models\Facility;
use Yii;
use yii\base\Model;

/**
 * Запрос на импорт данных
 * Class ExchangeRequest
 * @package app\modules\itrack\models\exchange\requests
 */
class ExchangeRequest extends Model
{
    public $user;
    public $senderObject;
    public $recipientObject;
    public $orderNumber;
    public $productionDate;
    public $invoiceNumber;
    public $invoiceDate;
    public $product;
    public $aggregatedData;
    public $emptyData;

    public function rules() {
        return [
            [[
                'user',
                'recipientObject',
                'orderNumber',
                'productionDate'
            ], 'required'],
            [['recipientObject'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::className(), 'targetAttribute' => ['recipientObject' => 'id']],
            [['senderObject', 'aggregatedData', 'emptyData'], 'safe'],
            [['invoiceNumber', 'invoiceDate'], 'required', 'when' => function($model) {
                return ($model->senderObject !== null && $model->senderObject !== $model->recipientObject) ? true : false;
            }],
            [['product'], 'required', 'when' => function($model) {
                return ($model->emptyData !== null) ? true : false;
            }],
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        if ($this->aggregatedData !== null) {
            $aggregatedData = Yii::createObject(ExchangeAggregatedData::className());
            $aggregatedData->attributes = $this->aggregatedData;
            $aggregatedData->commonProduct = $this->product;
            if (!$aggregatedData->validate()) {
                throw new SerializeException($aggregatedData->getErrors());
            }
        }

        return parent::validate($attributeNames, $clearErrors);
    }


}