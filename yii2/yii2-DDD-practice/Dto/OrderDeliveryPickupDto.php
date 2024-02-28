<?php

namespace common\components\order\Dto;

use common\components\order\Model\Order;

class OrderDeliveryPickupDto
{
	public int $shopId;
	public int $optUserId;
	public int $orderId;
	public string $deliveryDate;
	public string $comment;
	
	public function __construct(array $data, Order $order)
	{
		$this->optUserId    = $order->opt_user_id;
		$this->orderId      = $order->id;
		$this->shopId       = $data['shopId'] ?? '';
		$this->deliveryDate = $data['deliveryDate'] ?? '';
		$this->comment      = $data['comment'] ?? '';
	}
	
}