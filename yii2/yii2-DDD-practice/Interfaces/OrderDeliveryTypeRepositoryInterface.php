<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDeliveryTypeDto;

interface OrderDeliveryTypeRepositoryInterface
{
    
    /**
     * Возвращает тип доставки заказа
     *
     * @param string $code
     */
    public function getDeliveryType(string $code);
}