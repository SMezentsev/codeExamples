<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\ServiceFactoryDto;

interface OrderServiceInterface
{
	/**
	 * Возвращает заказ
	 *
	 * @param $orderServiceDto ServiceFactoryDto
	 *
	 * @return OrderInterface
	 */
	public function create(ServiceFactoryDto $orderServiceDto): OrderInterface;
	
	/**
	 * Устанавливает статус для заказа
	 *
	 * @param int         $orderId
	 * @param int         $statusId
	 * @param string|null $statusMessage
	 *
	 * @return void
	 */
	public function setOrderStatus(int $orderId, int $statusId, ?string $statusMessage = null): void;
	
	/**
	 * Логируем события
	 *
	 * @param OrderEventInterface $event
	 *
	 * @return void
	 */
	public function event(OrderEventInterface $event): void;
}