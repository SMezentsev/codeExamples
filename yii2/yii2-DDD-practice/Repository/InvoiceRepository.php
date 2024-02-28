<?php

namespace common\components\order\Repository;

use common\components\order\Interfaces\InvoiceRepositoryInterface;
use common\Exceptions\Interfaces\ValidationErrorInterface;
use common\Exceptions\ValidationErrorException;
use Dotenv\Exception\ValidationException;
use common\components\order\Dto\InvoiceDto;
use common\components\order\Model\Invoice;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    /**
     * Сохраняет счет
     *
     * @param InvoiceDto $dto
     *
     */
    public function create(InvoiceDto $dto): Invoice
    {
        $invoice = new Invoice(
            [
                'number' => $dto->number,
            ]
        );
        
        if (!$invoice->save()) {
            throw ValidationErrorException::create($invoice->errors);
        }
        
        return $invoice;
    }
}