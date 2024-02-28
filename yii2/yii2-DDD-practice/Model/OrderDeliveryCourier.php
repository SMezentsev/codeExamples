<?php

namespace common\components\order\Model;

use common\components\order\Interfaces\OrderShipmentInterface;
use common\models\OptUserAddress;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 *
 * @property integer        $order_id
 * @property string         $delivery_date
 * @property string         $delivery_time_from
 * @property string         $delivery_time_to
 * @property string         $courier_name
 * @property string         $courier_phone
 * @property string         $comment
 * @property integer        $users_address_id
 *
 * @property OptUserAddress $userAddress
 */
class OrderDeliveryCourier extends ActiveRecord implements OrderShipmentInterface
{
	
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%order_delivery_courier}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['user_address_id', 'order_id', 'delivery_time_id'], 'required'],
			[['user_address_id', 'order_id', 'delivery_time_id'], 'integer'],
			[['comment', 'courier_name', 'delivery_date', 'courier_phone'], 'string'],
			[['delivery_time_id'], 'exist', 'targetClass' => DeliveryTime::class, 'targetAttribute' => 'id'],
			[['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
			[['user_address_id'], 'exist', 'targetClass' => OptUserAddress::class, 'targetAttribute' => 'id'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'order_id'         => 'ID заказа',
			'user_address_id'  => 'Адрес пользователя',
			'comment'          => 'Комментарий пользователя',
			'delivery_date'    => 'Дата доставки заказа',
			'delivery_time_id' => 'ID времени заказа',
			'courier_name'     => 'ФИО курьера/водителя',
			'courier_phone'    => 'Телефон курьера/водителя',
		];
	}
	
	public function getUserAddress(): ActiveQueryInterface
	{
		return $this->hasOne(OptUserAddress::class, ['id' => 'user_address_id']);
	}
	
	public function extraFields(): array
	{
		return [
			'userAddress',
		];
	}
}