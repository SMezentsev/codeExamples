<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDeliveryTransportCompanyDto;
use common\components\order\Model\Order;
use common\components\order\Model\OrderDeliveryTransportCompany;

interface OrderDeliveryTransportCompanyRepositoryInterface
{
    /**
     * Возвращает доставку курьером по его ID
     *
     * @param $id integer ID заказа
     */
    public function findById($id);
    
    /**
     * Сохраняет доставку курьером
     *
     * @param OrderDeliveryTransportCompanyDto $dto
     *
     */
    public function save(OrderDeliveryTransportCompanyDto $dto): OrderDeliveryTransportCompany;
}