<?php

namespace common\components\order\Dto;

use common\components\order\Interfaces\OrderEventInterface;
use DateTimeImmutable;
use DateTimeInterface;

class OrderEventDto implements OrderEventInterface
{
	/**
	 * @var int
	 */
	private $orderId;
	/**
	 * @var string
	 */
	private $event;
	/**
	 * @var DateTimeInterface
	 */
	private $createdAt;
	/**
	 * @var array
	 */
	private $payload;
	
	/**
	 * @param int                    $orderId
	 * @param string                 $event
	 * @param array                  $payload
	 * @param DateTimeInterface|null $createdAt
	 */
	public function __construct(int $orderId, string $event, array $payload, ?DateTimeInterface $createdAt = null)
	{
		$this->orderId   = $orderId;
		$this->event     = $event;
		$this->payload   = $payload;
		$this->createdAt = $createdAt ?? new DateTimeImmutable();
	}
	
	/**
	 * @return int
	 */
	public function getOrderId(): int
	{
		return $this->orderId;
	}
	
	/**
	 * @return string
	 */
	public function getEvent(): string
	{
		return $this->event;
	}
	
	/**
	 * @return DateTimeImmutable|DateTimeInterface
	 */
	public function getCreatedAt(): DateTimeInterface
	{
		return $this->createdAt;
	}
	
	/**
	 * @return array
	 */
	public function getPayload(): array
	{
		return $this->payload ?? [];
	}
	
}