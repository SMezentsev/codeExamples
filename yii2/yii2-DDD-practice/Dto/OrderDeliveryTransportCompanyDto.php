<?php

namespace common\components\order\Dto;

use common\components\order\Model\Order;

class OrderDeliveryTransportCompanyDto
{
	public int $orderId;
	public string $engName;
	public string $comment;
	public string $city;
	public string $userTransportCompany;
	
	public function __construct(array $data, Order $order)
	{
		$this->orderId = $order->id;
		//TODO переделать чтоб с фронта падал айди транспортной компании из таблицы delivery_company
		$this->engName              = $data['eng_name'];
		$this->comment              = $data['comment'];
		$this->city                 = $data['userCity'];
		$this->userTransportCompany = $data['userTransportCompany'];
	}
}