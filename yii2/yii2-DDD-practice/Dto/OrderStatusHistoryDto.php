<?php

namespace common\components\order\Dto;

use common\components\order\Model\Order;

class OrderStatusHistoryDto
{
	public int $orderId;
	public string $StatusId;
	
	public function __construct(Order $order, ?OrderStatus $status = null)
	{
		$this->orderId = $order->id;
		if ($status) {
			$this->statusId = $status->id;
		}
	}
}