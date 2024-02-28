<?php

namespace common\components\order\Model;

use common\components\order\Interfaces\OrderInterface;
use common\components\order\Model\Query\OrderQuery;
use common\models\DeliveryCompany;
use common\models\OptUser;
use common\models\OptUserAddress;
use common\models\query\OptUserQuery;
use common\models\Shop;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int                           $opt_user_id
 * @property int                           $order_delivery_type_id
 * @property int                           $order_payment_method_id
 * @property int                           $code_1c
 *
 * @property OrderDeliveryType             $deliveryType
 * @property OrderDeliveryPickup           $orderPickup
 * @property OrderDeliveryCourier          $orderCourier
 * @property OrderDeliveryTransportCompany $orderTransportCompany
 *
 * @property OrderPaymentMethod            $paymentMethod
 */
class Order extends ActiveRecord implements OrderInterface
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName(): string
	{
		return '{{%order}}';
	}
	
	/**
	 * @return OrderQuery
	 */
	public static function find(): OrderQuery
	{
		return new OrderQuery(static::class);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rules(): array
	{
		return [
			[['opt_user_id', 'order_delivery_type_id', 'order_payment_method_id'], 'required'],
			[['payment_status_id', 'delivery_status_id', 'price'], 'integer'],
			[['shipping_date', 'reserve_date'], 'string'],
			[['order_delivery_type_id'], 'exist', 'targetClass' => OrderDeliveryType::class, 'targetAttribute' => 'id'],
			[['order_payment_method_id'], 'exist', 'targetClass' => OrderPaymentMethod::class, 'targetAttribute' => 'id'],
			[['opt_user_id'], 'exist', 'targetClass' => OptUser::class, 'targetAttribute' => 'id'],
			[['comment', 'goodQuantity', 'goodPrice', 'goodTotalPrice'], 'safe'],
		
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels(): array
	{
		return [
			'opt_user_id'        => 'User ID',
			'status_history'     => 'Идентификатор типа доставки',
			'payment_method'     => 'Модель типа оплаты',
			'payment_status_id'  => 'Статус оплаты',
			'delivery_status_id' => 'Статус доставки',
			'shipping_date'      => 'Дата отгрузки',
			'reserve_date'       => 'Дата окончания резерва',
			'code_1c'            => 'Код 1С',
			'created_at'         => 'Дата заказа',
			'price'              => 'Стоимость заказа',
			'comment'            => 'Комментарий',
		];
	}
	
	//todo перенести это в ActiveQuery
	public function user($userId)
	{
		$this->andWhere(['opt_user_id' => $userId]);
	}
	
	//todo перенести это в ActiveQuery
	public static function findById($id)
	{
		return self::findOne(['id' => $id]);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getDeliveryType(): ActiveQueryInterface
	{
		return $this->hasOne(OrderDeliveryType::class, ['id' => 'order_delivery_type_id']);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getPaymentMethod(): ActiveQueryInterface
	{
		return $this->hasOne(OrderPaymentMethod::class, ['id' => 'order_payment_method_id']);
	}
	
	/**
	 * @return ActiveQueryInterface|OptUserQuery
	 */
	public function getUserModel(): ActiveQueryInterface
	{
		return $this->hasOne(OptUser::class, ['id' => 'opt_user_id']);
	}
	
	/**
	 * @return ActiveQueryInterface|ActiveQuery
	 */
	public function getGoodsModels(): ActiveQueryInterface
	{
		return $this->hasMany(OrderGood::class, ['order_id' => 'id']);
	}
	
	//todo это должна быть связь
	public function getGoods($model)
	{
		return OrderGood::find()->where(['order_id' => $model->id])->all();
	}
	
	//todo это должна быть связь
	public function getDeliveryByPickup($model)
	{
		return OrderDeliveryPickup::find()->where(['order_id' => $model->id])->one();
	}
	
	//todo это должна быть связь
	public function getDeliveryByCourier($model)
	{
		return OrderDeliveryCourier::find()->where(['order_id' => $model->id])->one();
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getOrderInvoice(): ActiveQueryInterface
	{
		return $this->hasMany(OrderInvoice::class, ['order_id' => 'id']);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getInvoice(): ActiveQueryInterface
	{
		return $this->hasMany(Invoice::class, ['id' => 'invoice_id'])->via('orderInvoice');
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getOrderPickup(): ActiveQueryInterface
	{
		return $this->hasOne(OrderDeliveryPickup::class, ['order_id' => 'id']);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getShopAddress(): ActiveQueryInterface
	{
		return $this->hasOne(Shop::class, ['shop_id' => 'shop_id'])->via('orderPickup');
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getOrderCourier(): ActiveQueryInterface
	{
		return $this->hasOne(OrderDeliveryCourier::class, ['order_id' => 'id']);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getOrderTransportCompany(): ActiveQueryInterface
	{
		return $this->hasOne(OrderDeliveryTransportCompany::class, ['order_id' => 'id']);
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getUserAddress(): ActiveQueryInterface
	{
		return $this->hasOne(OptUserAddress::class, ['id' => 'user_address_id'])->via('orderCourier');
	}
	
	//todo это должна быть связь
	public function getTransportCompany($delivery_company_id)
	{
		return DeliveryCompany::find()->where(['id' => $delivery_company_id])->one();
	}
	
	//todo это должна быть связь
	public function getDeliveryByTransportCompany($model)
	{
		return OrderDeliveryTransportCompany::find()->where(['order_id' => $model->id])->one();
	}
	
	/**
	 * @return ActiveQuery|ActiveQueryInterface
	 */
	public function getOrderStatusHistory(): ActiveQueryInterface
	{
		return $this->hasMany(OrderStatusHistory::class, ['order_id' => 'id']);
	}
	
	// это не тут, надо в сервис
	public function getStatus(int $status_category_id)
	{
		return $this->hasOne(OrderStatus::class, ['id' => 'payment_status_id']);
	}
	
	public function getDeliveryStatus()
	{
		return $this->hasOne(OrderStatus::class, ['id' => 'delivery_status_id']);
	}
	
	public function getPaymentStatus()
	{
        return $this->hasOne(OrderStatus::class, ['id' => 'payment_status_id']);
	}
	
	public function fields(): array
	{
		return [
			'id',
			'opt_user_id',
			'shipping_date',
			'order_payment_method_id',
			'order_delivery_type_id',
			'payment_status_id',
			'delivery_status_id',
			'reserve_date',
			'created_at',
			'code_1c',
			'comment',
			'shopAddress',
			'userAddress',
			'deliveryType',
			'paymentMethod',
			
			'paymentStatus',
			'deliveryStatus',
			'status_message',
			
			'goods' => static function (self $model) {
				return $model->getGoods($model);
			},
			
			'invoice' => static function (self $model) {
				return $model->getInvoice()->addOrderBy(['created_at' => SORT_DESC])->one();
			},
			
			'price' => static function (self $model) {
				return $model->price / 100;
			},
			
			'totalPriceRub' => static function (self $model) {
				return $model->price / 100;
			},
		];
	}
	
	public function extraFields(): array
	{
		$extraFields = [
			//            'shopAddress',
			//            'userAddress',
			//            'deliveryType',
			//            'paymentMethod',
		];
		
		switch ($this->order_delivery_type_id) {
			case OrderDeliveryType::PICKUP:
				$extraFields['pickup'] = static function (self $model) {
					return $model->getDeliveryByPickup($model);
				};
				break;
			case OrderDeliveryType::CITY_REGION:
				$extraFields['delivery'] = static function (self $model) {
					return $model->getDeliveryByCourier($model);
				};
				break;
			case OrderDeliveryType::RUSSIAN_TS:
				$extraFields['transportCompany'] = static function (self $model) {
					$deliveryModel = $model->getDeliveryByTransportCompany($model);
					if ($deliveryModel) {
						return $model->getTransportCompany($deliveryModel->delivery_company_id);
					}
					
					return [];
				};
				break;
		}
		
		return $extraFields;
	}
	
	/**
	 * @param bool $insert
	 *
	 * @return bool
	 */
	public function beforeSave($insert)
	{
		if ($insert && $this->created_at === '') {
			$this->created_at = new Expression('NOW()');
		}
		
		return parent::beforeSave($insert);
	}
	
	// getters
	
	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}
	
}