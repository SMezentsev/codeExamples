<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderDeliveryCourierDto;
use common\components\order\Dto\OrderDeliveryPickupDto;
use common\components\order\Dto\OrderDeliveryTransportCompanyDto;
use common\components\order\Interfaces\OrderDeliveryCourierRepositoryInterface;
use common\components\order\Interfaces\OrderDeliveryPickupRepositoryInterface;
use common\components\order\Interfaces\OrderDeliveryRepositoryManagerInterface;
use common\components\order\Interfaces\OrderDeliveryTransportCompanyRepositoryInterface;
use common\components\order\Model\OrderDeliveryType;


class OrderDeliveryRepositoryManager implements OrderDeliveryRepositoryManagerInterface
{
	
	public $orderDeliveryPickupRepository;
	public $orderDeliveryCourierRepository;
	public $orderDeliveryTransportCompanyRepository;
	
	public function __construct(
		OrderDeliveryPickupRepositoryInterface           $orderDeliveryPickupRepository,
		OrderDeliveryCourierRepositoryInterface          $orderDeliveryCourierRepository,
		OrderDeliveryTransportCompanyRepositoryInterface $orderDeliveryTransportCompanyRepository
	)
	{
		$this->orderDeliveryPickupRepository           = $orderDeliveryPickupRepository;
		$this->orderDeliveryCourierRepository          = $orderDeliveryCourierRepository;
		$this->orderDeliveryTransportCompanyRepository = $orderDeliveryTransportCompanyRepository;
	}
	
	public function create(array $data, $order, $deliveryType)
	{
		if ($order) {
			switch ($deliveryType->id) {
				//сохраняем данные заказа в таблицу самовывоза
				case OrderDeliveryType::PICKUP:
					$this->orderDeliveryPickupRepository->save(new OrderDeliveryPickupDto($data, $order));
					break;
				//сохраняем данные заказа в таблицу доставки курьером
				case OrderDeliveryType::CITY_REGION:
					$this->orderDeliveryCourierRepository->save((new OrderDeliveryCourierDto($data, $order)));
					break;
				//сохраняем данные заказа в таблицу доставки транспортной компанией
				case OrderDeliveryType::RUSSIAN_TS:
					$this->orderDeliveryTransportCompanyRepository->save((new OrderDeliveryTransportCompanyDto($data, $order)));
					break;
			}
		}
	}
}