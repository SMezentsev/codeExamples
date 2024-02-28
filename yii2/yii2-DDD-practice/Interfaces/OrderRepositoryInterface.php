<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDto;
use common\components\order\Model\Order;

interface OrderRepositoryInterface
{
	/**
	 * Возвращает заказ по его ID
	 *
	 * @param Order    $order
	 * @param int|null $statusId
	 *
	 * @return OrderRepositoryInterface
	 */
	public function cancel(Order $order, ?int $statusId);
	
	/**
	 * Сохраняет заказ
	 *
	 * @param OrderDto $dto
	 *
	 * @return OrderInterface
	 */
	public function create(OrderDto $dto): OrderInterface;
	
	/**
	 * Сохраняет статус заказ
	 *
	 * @param int $id
	 * @param int $statusId
	 *
	 */
	public function setPaymentStatus(int $id, int $statusId);
	
	/**
	 * Сохраняет статус заказ
	 *
	 * @param int         $id
	 * @param int         $statusId
	 * @param string|null $statusMessage Сообщение статуса
	 */
	public function setDeliveryStatus(int $id, int $statusId, ?string $statusMessage = null): void;
	
	/**
	 * Получение модели заказа
	 *
	 * @param int $id
	 *
	 * @return Order
	 */
	public function getOrder(int $id): Order;
	
}