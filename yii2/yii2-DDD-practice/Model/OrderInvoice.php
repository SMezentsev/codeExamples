<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $number
 * @property string $code1C
 */
class OrderInvoice extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_invoice}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['order_id', 'invoice_id'], 'required'],
			[['order_id', 'invoice_id'], 'integer'],
			[['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
			[['invoice_id'], 'exist', 'targetClass' => Invoice::class, 'targetAttribute' => 'id'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'order_id'   => 'ID заказа',
			'invoice_id' => 'ID в 1С',
		];
	}
}