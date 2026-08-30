<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use InvalidArgumentException;

final readonly class PaymentId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Payment IDs must be positive.');
        }
    }
}
