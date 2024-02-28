<?php

namespace common\components\order\Repository;

use common\components\order\Interfaces\OrderInvoiceRepositoryInterface;
use common\Exceptions\ValidationErrorException;
use common\components\order\Model\Order;
use common\components\order\Model\Invoice;
use common\components\order\Model\OrderInvoice;
use common\components\order\Dto\OrderInvoiceDto;

class OrderInvoiceRepository implements OrderInvoiceRepositoryInterface
{
    /**
     * Сохраняет приязку заказа и счета
     *
     * @param OrderInvoiceDto $dto
     *
     */
    public function create(OrderInvoiceDto $dto): OrderInvoice
    {
        $orderInvoice = new OrderInvoice(
            [
                'order_id'   => $dto->orderId,
                'invoice_id' => $dto->invoiceId,
            ]
        );
        
        if (!$orderInvoice->save()) {
            throw ValidationErrorException::create($orderInvoice->errors);
        }
        
        return $orderInvoice;
    }
}