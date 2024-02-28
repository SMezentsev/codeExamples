<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderDeliveryCourierDto;
use common\components\order\Interfaces\OrderDeliveryCourierRepositoryInterface;
use common\components\order\Model\DeliveryTime;
use common\components\order\Model\OrderDeliveryCourier;
use common\Exceptions\ValidationErrorException;

class OrderDeliveryCourierRepository implements OrderDeliveryCourierRepositoryInterface
{
	public const DEFAULT_TIME = 'default0918';
	
	/**
	 * Сохраняет заказ
	 *
	 * @param OrderDeliveryCourierDto $dto
	 *
	 * @return OrderDeliveryCourier
	 * @throws ValidationErrorException
	 */
	public function save(OrderDeliveryCourierDto $dto): OrderDeliveryCourier
	{
		$delivery = new OrderDeliveryCourier(
			[
				'order_id'         => $dto->orderId,
				'user_address_id'  => $dto->userAddressId,
				'delivery_date'    => $dto->deliveryDate,
				'delivery_time_id' => $dto->schedule == self::DEFAULT_TIME ? DeliveryTime::TIME_FIRST : DeliveryTime::TIME_SECOND,
				'comment'          => $dto->comment,
			]
		);
		
		if (!$delivery->save()) {
			throw ValidationErrorException::create($delivery->errors);
		}
		
		return $delivery;
	}
}