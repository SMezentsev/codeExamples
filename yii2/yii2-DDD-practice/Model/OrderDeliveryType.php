<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $name Наименование
 * @property string $code Код типа получения заказа
 */
class OrderDeliveryType extends ActiveRecord
{
	public const PICKUP      = 1;
	public const CITY_REGION = 2;
	public const RUSSIAN_TS  = 3;
	
	public const PICKUP_METHOD      = 'pickup';
	public const CITY_REGION_METHOD = 'city_region';
	public const RUSSIAN_TS_METHOD  = 'russia_tc';
	
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_delivery_type}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public static function getTypeByCode($code)
	{
		if (!empty($code)) {
			return self::find()->where(['code' => $code])->one();
		}
		
		return false;
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