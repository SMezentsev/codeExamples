<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDeliveryPickupDto;
use common\components\order\Model\OrderDeliveryPickup;

interface OrderDeliveryPickupRepositoryInterface
{
    /**
     * Сохраняет доставку курьером
     *
     * @param OrderDeliveryPickupDto $dto
     *
     */
    public function save(OrderDeliveryPickupDto $dto): OrderDeliveryPickup;
}