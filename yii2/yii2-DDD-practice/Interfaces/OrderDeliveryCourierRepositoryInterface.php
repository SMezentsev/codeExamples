<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDeliveryCourierDto;
use common\components\order\Model\OrderDeliveryCourier;

interface OrderDeliveryCourierRepositoryInterface
{
    /**
     * Сохраняет доставку курьером
     *
     * @param OrderDeliveryCourierDto $dto
     *
     */
    public function save(OrderDeliveryCourierDto $dto): OrderDeliveryCourier;
}