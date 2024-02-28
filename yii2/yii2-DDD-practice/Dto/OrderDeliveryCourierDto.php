<?php

namespace common\components\order\Dto;

use common\components\order\Model\Order;

class OrderDeliveryCourierDto
{
	public int $userAddressId;
	public int $orderId;
	public string $comment;
	public string $schedule;
	public string $deliveryDate;
	
	public function __construct(array $data, Order $order)
	{
		$this->orderId       = $order->id;
		$this->userAddressId = $data['userAddressId'];
		$this->comment       = $data['comment'];
		$this->schedule      = $data['schedule'];
		$this->deliveryDate  = $data['deliveryDate'];
	}
	
}