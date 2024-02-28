<?php

namespace common\components\order\Interfaces;

interface OrderEventRepositoryInterface
{
	
	public function event(OrderEventInterface $event): void;
	
}