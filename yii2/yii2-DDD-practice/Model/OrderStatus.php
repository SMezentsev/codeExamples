<?php

namespace common\components\order\Model;

use common\components\order\Model\Query\OrderStatusQuery;
use yii\db\ActiveRecord;

/**
 *
 * @property string $name
 */
class OrderStatus extends ActiveRecord
{
	
	public const DEFAULT_STATUS     = 1;
	public const NOT_DEFAULT_STATUS = 2;
	public const STATUS_CANCEL      = 5;
	public const STATUS_DELIVERED   = 9;
	public const STATUS_PAID        = 2;
	public const STATUS_NOT_PAID    = 1;
	
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_status}}';
	}
	
	public static function find(): OrderStatusQuery
	{
		return new OrderStatusQuery(static::class);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rules(): array
	{
		return [
			[['title', 'status_category_id'], 'required'],
			['is_default', 'default', 'value' => self::DEFAULT_STATUS],
			['is_default', 'in', 'range' => [self::DEFAULT_STATUS, self::NOT_DEFAULT_STATUS]],
			[['status_category_id'], 'exist', 'targetClass' => OrderStatusCategory::class, 'targetAttribute' => 'id'],
			[['code_1c'], 'string', 'max' => 40],
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels(): array
	{
		return [
			'title'              => 'Название',
			'code'               => 'Код статуса',
			'is_default'         => 'Дефолтный статус',
			'status_category_id' => 'ID статуса',
			'code_1c'            => 'Кода 1С',
		];
	}
}