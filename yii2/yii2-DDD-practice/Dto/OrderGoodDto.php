<?php

namespace common\components\order\Dto;

class OrderGoodDto
{
	public int $orderId;
	public int $goodId;
	public int $goodCategoryId;
	public int $quantity;
	public int $price;
    public ?string $createdAt;
	
    public function __construct($orderId, $goodId, $goodCategoryId, $quantity, $price, $createdAt)
	{
		$this->orderId        = $orderId;
		$this->goodId         = $goodId;
		$this->goodCategoryId = $goodCategoryId;
		$this->quantity       = $quantity;
		$this->price          = $price;
        $this->createdAt     = $createdAt;
	}
}