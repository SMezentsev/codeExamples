<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $name
 */
class OrderPaymentMethod extends ActiveRecord
{
	
	public const CASH    = 1;
	public const INVOICE = 2;
	
	public const METHOD_INVOICE = 'invoice';
	
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_payment_method}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['name'], 'string'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'name' => 'Наименование',
		];
	}
	
}