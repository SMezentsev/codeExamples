<?php

namespace common\components\order\Model;

use common\components\order\Interfaces\OrderShipmentInterface;
use common\models\DeliveryCompany;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 *
 * @property integer $order_id
 * @property integer $delivery_company_id
 * @property string  $comment
 * @property string  $city
 * @property string  $user_transport_company
 *
 * @property DeliveryCompany $transportCompany
 */
class OrderDeliveryTransportCompany extends ActiveRecord implements OrderShipmentInterface
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%order_delivery_transport_company}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['order_id', 'delivery_company_id'], 'required'],
			[['order_id', 'delivery_company_id'], 'integer'],
			[['comment', 'city', 'user_transport_company'], 'string'],
			[['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
			[['delivery_company_id'], 'exist', 'targetClass' => DeliveryCompany::class, 'targetAttribute' => 'id'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'order_id'               => 'ID заказа',
			'delivery_company_id'    => 'Идентификатор транспортной компании',
			'comment'                => 'Комментарий пользователя',
			'city'                   => 'Город пользователя',
			'user_transport_company' => 'Транспортная компания пользователя',
		];
	}
	
	/**
	 * @return ActiveQueryInterface|ActiveQuery
	 */
	public function getTransportCompany(): ActiveQueryInterface
	{
		return $this->hasOne(DeliveryCompany::class, ['id' => 'delivery_company_id']);
	}
	
	public function extraFields(): array
	{
		return [
			'transportCompany',
		];
	}
}