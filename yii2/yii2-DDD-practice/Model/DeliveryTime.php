<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $time_from
 * @property string $time_to
 * @property string $description
 */
class DeliveryTime extends ActiveRecord
{
	public const TIME_FIRST  = 1;
	public const TIME_SECOND = 2;
	
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%delivery_time}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['time_from', 'time_to', 'description'], 'string'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'time_from'   => 'Время доставки, с',
			'time_to'     => 'Время доставки, по',
			'description' => 'Описание',
		];
	}
}