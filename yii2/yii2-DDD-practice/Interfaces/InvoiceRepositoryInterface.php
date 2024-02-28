<?php

namespace common\components\order\Interfaces;

use common\components\order\Model\Invoice;
use common\components\order\Dto\InvoiceDto;

interface InvoiceRepositoryInterface
{
    /**
     * Сохраняет счет
     *
     * @param InvoiceDto $dto
     *
     * @return Invoice
     */
    
    public function create(InvoiceDto $dto): Invoice;
}