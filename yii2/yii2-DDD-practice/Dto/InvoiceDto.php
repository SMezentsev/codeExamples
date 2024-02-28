<?php

namespace common\components\order\Dto;

class InvoiceDto
{
	public string $number;
	
	public function __construct(string $code1c)
	{
		$this->number = $code1c;
	}
}