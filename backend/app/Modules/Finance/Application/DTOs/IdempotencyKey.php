<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use InvalidArgumentException;

final readonly class IdempotencyKey
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 128) {
            throw new InvalidArgumentException('Idempotency keys must contain between 1 and 128 bytes.');
        }

        $this->value = $value;
    }

    public function hash(): string
    {
        return hash('sha256', $this->value);
    }
}
