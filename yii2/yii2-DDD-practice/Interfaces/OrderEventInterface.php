<?php

namespace common\components\order\Interfaces;

use DateTimeInterface;

interface OrderEventInterface
{
	
	public const EVENT_1C_REQUEST          = 'request.1c';
	public const EVENT_1C_RESPONSE_SUCCESS = 'response.1c.success';
	public const EVENT_1C_RESPONSE_FAIL    = 'response.1c.fail';
	public const EVENT_VALIDATE_FAIL       = 'validate.fail';
	
	public function getOrderId(): int;
	
	public function getEvent(): string;
	
	public function getCreatedAt(): DateTimeInterface;
	
	public function getPayload(): array;
	
}