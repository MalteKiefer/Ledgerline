<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

final readonly class CancelInvoiceData
{
    public function __construct(public InvoiceId $invoiceId) {}
}
