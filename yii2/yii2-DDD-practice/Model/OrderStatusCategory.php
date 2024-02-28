<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $name
 */
class OrderStatusCategory extends ActiveRecord
{
	
	public const CATEGORY_PAYMENT  = 1;
	public const CATEGORY_DELIVERY = 2;
	
	
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_status_category}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['name'], 'string'],
			[['created_at'], 'integer'],
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