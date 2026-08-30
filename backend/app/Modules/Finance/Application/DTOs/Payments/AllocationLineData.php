<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use InvalidArgumentException;

final readonly class AllocationLineData
{
    private const MAX_MINOR = 99_999_999_999_999;

    public function __construct(public InvoiceId $invoiceId, public int $amountMinor)
    {
        if ($amountMinor === 0) {
            throw new InvalidArgumentException('Allocation amount must not be zero.');
        }
        if ($amountMinor > self::MAX_MINOR || $amountMinor < -self::MAX_MINOR) {
            throw new InvalidArgumentException('Allocation amount exceeds the supported minor-unit range.');
        }
    }
}
