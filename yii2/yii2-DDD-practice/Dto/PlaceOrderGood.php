<?php

namespace common\components\order\Dto;

use common\components\order\Interfaces\PlaceOrderGoodInterface;

class PlaceOrderGood implements PlaceOrderGoodInterface
{
	/**
	 * @var string
	 */
	private $type;
	/**
	 * @var string
	 */
	private $identity;
	/**
	 * @var int
	 */
	private $quantity;
	/**
	 * @var int|null
	 */
	private $price;
	
	/**
	 * @var string|null
	 */
	private $autoPartCategoryId;
	
	/**
	 * @param string   $type
	 * @param string   $identity
	 * @param int      $quantity
	 * @param int|null $price
	 */
	public function __construct(string $type, string $identity, int $quantity, ?int $price)
	{
		$this->type     = $type;
		$this->identity = $identity;
		$this->quantity = $quantity;
		$this->price    = $price;
	}
	
	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}
	
	/**
	 * @return string
	 */
	public function getIdentity(): string
	{
		return $this->identity;
	}
	
	/**
	 * @return int
	 */
	public function getQuantity(): int
	{
		return $this->quantity;
	}
	
	/**
	 * @return int|null
	 */
	public function getPrice(): ?int
	{
		return $this->price;
	}
	
	/**
	 * @param string|null $autoPartCategoryId
	 *
	 * @return PlaceOrderGoodInterface
	 */
	public function setAutoPartCategoryId(?string $autoPartCategoryId): PlaceOrderGoodInterface
	{
		$this->autoPartCategoryId = $autoPartCategoryId;
		
		return $this;
	}
	
	public function getAutoPartCategoryId(): ?string
	{
		return $this->autoPartCategoryId;
	}
	
	
}