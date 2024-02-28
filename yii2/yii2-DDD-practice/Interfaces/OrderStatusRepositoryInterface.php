<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderStatusDto;
use common\components\order\Model\OrderStatus;

interface OrderStatusRepositoryInterface
{
	/**
	 * Сохраняет статус заказа
	 *
	 * @param OrderStatus $status
	 *
	 */
	public function create(OrderStatusDto $dto);
	
	/**
	 * Сохраняет дефолтный статус в историю заказа
	 *
	 * @param int   $orderId
	 * @param array $categories
	 *
	 */
	public function setDefaultStatus(int $orderId, array $categories);
	
	/**
	 * Возвращает статус
	 *
	 * @param int $orderCategoryId
	 *
	 */
	public function getDefaultStatus(int $statusCategoryId): OrderStatus;
	
	/**
	 * Сохраняет статус в историю заказа
	 *
	 * @param int   $orderId
	 * @param array $categories
	 *
	 */
	public function setStatus(int $orderId, int $statusId): OrderStatus;
	
	/**
	 * Возвращает статус
	 *
	 * @param int $statusId
	 *
	 */
	public function getStatus(int $statusId): OrderStatus;
	
}