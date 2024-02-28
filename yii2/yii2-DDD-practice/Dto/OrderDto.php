<?php

namespace common\components\order\Dto;

class OrderDto
{
	public int $optUserId;
	public string $orderPaymentMethodId;
	public int $deliveryTypeId;
	public ?string $code1c;
	public array $cart;
	public int $price;
	public ?string $shippingDate;
	public ?string $reserveDate;
	public ?string $createdAt;
	public ?string $comment;
	
	public function __construct(
		int     $optUserId,
		?string $code1c,
		int     $deliveryTypeId,
		string  $orderPaymentMethodId,
		array   $cart,
		int     $price,
		?string $shipping_date,
		?string $reserve_date,
		?string $createdAt,
		?string $comment
	)
	{
		$this->optUserId            = $optUserId;
		$this->code1c               = $code1c;
		$this->deliveryTypeId       = $deliveryTypeId;
		$this->orderPaymentMethodId = $orderPaymentMethodId;
		$this->cart                 = $cart;
		$this->price                = $price;
		$this->shippingDate         = $shipping_date;
		$this->reserveDate          = $reserve_date;
		$this->createdAt            = $createdAt;
		$this->comment              = $comment;
	}
}