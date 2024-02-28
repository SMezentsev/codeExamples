<?php

namespace common\components\order\Interfaces;

use common\components\order\Model\Order;
use common\components\order\Model\Invoice;
use common\components\order\Model\OrderInvoice;
use common\components\order\Dto\OrderInvoiceDto;

interface OrderInvoiceRepositoryInterface
{
    /**
     * Сохраняет приязку заказа и счета
     *
     * @param OrderInvoiceDto $dto
     *
     * @return OrderInvoice
     */
    
    public function create(OrderInvoiceDto $dto): OrderInvoice;
}