<?php

namespace common\components\order\Queue;

interface OrderQueueManagerInterface
{
	
	/**
	 * Опубликовать сообщение о размещении в очереди
	 *
	 * @param int $orderId
	 *
	 * @return string|null
	 */
	public function placeOrder(int $orderId): ?string;
	
}
