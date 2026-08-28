<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use InvalidArgumentException;

final readonly class DeliveryId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Delivery IDs must be positive.');
        }
    }
}
