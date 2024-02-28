<?php

namespace common\components\order\Model;

use common\components\order\Model\Query\OrderEventQuery;
use yii\db\ActiveRecord;

class OrderEvent extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_event}}';
	}
	
	/**
	 * @return OrderEventQuery
	 */
	public static function find(): OrderEventQuery
	{
		return new OrderEventQuery(static::class);
	}
	
}
