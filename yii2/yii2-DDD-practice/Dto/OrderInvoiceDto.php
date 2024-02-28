<?php

namespace common\components\order\Dto;

class OrderInvoiceDto
{
	public int $orderId;
	public int $invoiceId;
	
	public function __construct(int $orderId, int $invoiceId)
	{
		$this->orderId   = $orderId;
		$this->invoiceId = $invoiceId;
	}
}