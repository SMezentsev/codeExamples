<?php

namespace common\components\order\Factory;

use common\components\Delivery;
use common\components\order\Dto\PlaceOrderGood;
use common\components\order\Interfaces\OrderInterface;
use common\components\order\Model\OrderDeliveryType;
use common\components\order\Model\OrderGood;
use common\components\order\Model\OrderPaymentMethod;
use common\components\orders\Order;
use common\models\forms\B2BOrderForm;
use common\models\OptUser;
use common\models\Region;
use DateTimeImmutable;
use Exception;
use Yii;
use yii\base\InvalidValueException;

class B2BOrderFormFactoryByOrder implements B2BOrderFormFactoryByOrderInterface
{
	
	/**
	 * @var Delivery|mixed|object|null
	 */
	private $deliveryComponent;
	
	public function __construct()
	{
		$this->deliveryComponent = Yii::$app->delivery;
	}
	
	/**
	 * @param OrderInterface|\common\components\order\Model\Order $order
	 *
	 * @return B2BOrderForm
	 * @throws Exception
	 */
	public function createByOrder(OrderInterface $order): B2BOrderForm
	{
		/** @var OptUser $user */
		$user = $order->userModel;
		
		/** @var Region $region */
		$region = $user->region;
		
		$goods = $this->prepareGoods($order);
		
		$orderComponents = new Order($region, $user, $goods, $this->deliveryComponent->deliveryTypes);
		
		$orderForm = new B2BOrderForm($orderComponents);
		
		$orderParams = [
			'deliveryType' => $order->deliveryType->code,
			'delivery'     => $this->prepareShipment($order),
			//'bobuses'      => '',
		];
		
		$orderForm->load($orderParams, '');
		
		return $orderForm;
	}
	
	/**
	 * @param OrderInterface|Order $order
	 *
	 * @return array|string[]
	 */
	protected function prepareShipment(OrderInterface $order): array
	{
		$params = [];
		switch ($order->order_delivery_type_id) {
			case OrderDeliveryType::PICKUP:
				
				$pickup = $order->orderPickup;
				
				$params = [
					'shop' => $pickup->shop_id,
					
					'date'    => $this->prepareShipmentDate($pickup->delivery_date),
					'comment' => $pickup->comment,
					'payment' => $this->getPayment($order),
				];
				break;
			
			case OrderDeliveryType::CITY_REGION:
				
				$courier = $order->orderCourier;
				$address = $courier->userAddress;
				$params  = [
					
					'city'        => $address->address['city'] ?? null,
					'street'      => $address->address['street'] ?? null,
					'house'       => $address->address['house'] ?? null,
					'coords'      => $address->address['coords'] ?? null,
					'autoAddress' => $address->address['autoAddress'] ?? null,
					
					'schedule'        => null,
					'expressDelivery' => null,
					
					'date'    => $this->prepareShipmentDate($courier->delivery_date, 'Y-m-d'),
					'comment' => $courier->comment,
					'payment' => $this->getPayment($order),
				];
				break;
			
			case OrderDeliveryType::RUSSIAN_TS:
				
				$tc     = $order->orderTransportCompany;
				$params = [
					
					'userCity' => $tc->city,
					
					'userTc' => $tc->user_transport_company,
					'tc'     => $tc->transportCompany->eng_name,
					
					'comment' => $tc->comment,
					'payment' => $this->getPayment($order),
				];
				break;
		}
		
		return $params;
	}
	
	/**
	 * @param string $date
	 * @param string $parseFormat
	 *
	 * @return string
	 */
	protected function prepareShipmentDate(string $date, string $parseFormat = 'Y-m-d H:i:s'): string
	{
		return DateTimeImmutable::createFromFormat($parseFormat, $date)->format('Y-m-d\TH:i:s.u\Z');
	}
	
	/**
	 * @param OrderInterface|Order $order
	 *
	 * @return string|null
	 */
	protected function getPayment(OrderInterface $order): ?string
	{
		return [
			OrderPaymentMethod::CASH    => 'cash',
			OrderPaymentMethod::INVOICE => 'invoice',
		][$order->order_payment_method_id] ?? null;
	}
	
	/**
	 * @param OrderInterface|\common\components\order\Model\Order $order
	 *
	 * @return array
	 */
	protected function prepareGoods(OrderInterface $order): array
	{
		$typeMapper = [
			OrderGood::CATEGORY_TYRE => 'tyre',
			OrderGood::CATEGORY_DISK => 'disk',
		];
		
		$goodCollection = [];
		
		/** @var OrderGood $good */
		foreach ($order->goodsModels as $good) {
			$goodType = $typeMapper[$good->good_category_id] ?? null;
			if ($goodType === null) {
				throw new InvalidValueException(Yii::t('app', 'Неизвестный тип товара `{type}`', ['type' => $good->good_category_id]));
			}
			
			$goodCollection[] = new PlaceOrderGood($goodType, (string)$good->good_id, (int)$good->quantity, (int)$good->price);
		}
		
		return $goodCollection;
	}
	
}