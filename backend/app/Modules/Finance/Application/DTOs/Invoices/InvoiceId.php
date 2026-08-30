<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use InvalidArgumentException;

final readonly class InvoiceId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Invoice IDs must be positive.');
        }
    }
}
