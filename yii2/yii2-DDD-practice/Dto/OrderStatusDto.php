<?php

namespace common\components\order\Dto;

class OrderStatusDto
{
	public int $orderId;
	public int $statusId;
	
	public function __construct(int $orderId, int $statusId)
	{
		$this->orderId  = $orderId;
		$this->statusId = $statusId;
	}
}