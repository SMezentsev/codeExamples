<?php

namespace common\components\order\Interfaces;

use common\components\order\Model\Order;
use common\components\order\Model\OrderDeliveryType;

interface OrderDeliveryRepositoryManagerInterface
{
    /**
     * Сохраняет заказ
     *
     * @param array $data
     * @param Order $order
     * @param OrderDeliveryType $deliveryType
     *
     */
    public function create(array $data, Order $order, OrderDeliveryType $deliveryType);
}