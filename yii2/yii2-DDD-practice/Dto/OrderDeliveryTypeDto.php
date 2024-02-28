<?php

namespace common\components\order\Dto;

class OrderDeliveryTypeDto
{
	public string $name;
	public string $code;
	
	public function __construct(array $params)
	{
		$this->code = $params['code'];
	}
}