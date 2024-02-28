<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderDeliveryPickupDto;
use common\components\order\Interfaces\OrderDeliveryPickupRepositoryInterface;
use common\components\order\Model\OrderDeliveryPickup;
use common\Exceptions\ValidationErrorException;


class OrderDeliveryPickupRepository implements OrderDeliveryPickupRepositoryInterface
{
	/**
	 * Сохраняет заказ
	 *
	 * @param OrderDeliveryPickupDto $dto
	 *
	 * @return OrderDeliveryPickup
	 * @throws ValidationErrorException
	 */
	public function save(OrderDeliveryPickupDto $dto): OrderDeliveryPickup
	{
		$delivery = new OrderDeliveryPickup(
			[
				'order_id'      => $dto->orderId,
				'shop_id'       => $dto->shopId,
				'delivery_date' => $dto->deliveryDate,
				'comment'       => $dto->comment,
			]
		);
		
		if (!$delivery->save()) {
			throw ValidationErrorException::create($delivery->errors);
		}
		
		return $delivery;
	}
}