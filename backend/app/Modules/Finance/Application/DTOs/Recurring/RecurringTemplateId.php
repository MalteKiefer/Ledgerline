<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use InvalidArgumentException;

final readonly class RecurringTemplateId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Recurring template IDs must be positive.');
        }
    }
}
