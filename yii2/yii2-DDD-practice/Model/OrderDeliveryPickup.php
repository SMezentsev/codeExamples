<?php

namespace common\components\order\Model;

use common\components\order\Interfaces\OrderShipmentInterface;
use common\models\Shop;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 *
 * @property integer $order_id
 * @property string  $delivery_date
 * @property string  $comment
 * @property integer $shop_id
 */
class OrderDeliveryPickup extends ActiveRecord implements OrderShipmentInterface
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_delivery_pickup}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['shop_id', 'order_id'], 'required'],
			[['shop_id', 'order_id'], 'integer'],
			[['comment', 'delivery_date'], 'string'],
			[['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
			[['shop_id'], 'exist', 'targetClass' => Shop::class, 'targetAttribute' => 'shop_id'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'order_id'      => 'ID заказа',
			'delivery_date' => 'Дата доставки',
			'shop_id'       => 'Идентификатор магазина',
			'comment'       => 'Комментарий пользователя',
		];
	}
	
	/**
	 * @return ActiveQueryInterface
	 */
	public function getShop(): ActiveQueryInterface
	{
		return $this->hasOne(Shop::class, ['shop_id' => 'shop_id']);
	}
	
	public function extraFields(): array
	{
		return [
			'shop',
		];
	}
	
}