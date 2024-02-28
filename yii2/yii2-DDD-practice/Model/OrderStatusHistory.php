<?php

namespace common\components\order\Model;

use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 * @property bool   $is_default
 * @property int    $order_id
 * @property int    $order_status_id
 * @property string $code_1c
 */
class OrderStatusHistory extends ActiveRecord
{
	const DEFAULT_STATUS     = 1;
	const NOT_DEFAULT_STATUS = 0;
	
	/**
	 * {@inheritdoc}
	 */
	public static function tableName(): string
	{
		return '{{%order_status_history}}';
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rules(): array
	{
		return [
			[['order_id', 'order_status_id'], 'required'],
			[['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
			[['order_status_id'], 'exist', 'targetClass' => OrderStatus::class, 'targetAttribute' => 'id'],
			[['created_at'], 'safe'],
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels(): array
	{
		return [
			'order_id'        => 'Заказ',
			'order_status_id' => 'ID статуса',
			'created_at'      => 'Дата статуса',
		];
	}
	
	/**
	 * @return ActiveQueryInterface|ActiveQuery
	 */
	public function getStatus(): ActiveQueryInterface
	{
		return $this->hasMany(OrderStatus::class, ['id' => 'order_status_id']);
	}
	
	public function extraFields(): array
	{
		return [
			'status',
		];
	}
}