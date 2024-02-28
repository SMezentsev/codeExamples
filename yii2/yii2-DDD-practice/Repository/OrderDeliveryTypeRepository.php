<?php

namespace common\components\order\Repository;

use common\components\order\Interfaces\OrderDeliveryTypeRepositoryInterface;
use common\components\order\Model\OrderDeliveryType;
use common\components\order\Dto\OrderDeliveryTypeDto;

class OrderDeliveryTypeRepository implements OrderDeliveryTypeRepositoryInterface
{
    /**
     * Возвращает тип способа доставки
     */
    public function getDeliveryType(string $code)
    {
        return OrderDeliveryType::getTypeByCode($code);
    }
}