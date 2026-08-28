<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use InvalidArgumentException;

final readonly class AllocationLineData
{
    public function __construct(public InvoiceId $invoiceId, public int $amountMinor)
    {
        if ($amountMinor === 0) {
            throw new InvalidArgumentException('Allocation amount must not be zero.');
        }
    }
}
