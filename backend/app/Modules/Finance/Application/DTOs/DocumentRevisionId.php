<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use InvalidArgumentException;

final readonly class DocumentRevisionId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('A document revision id must be a positive integer.');
        }
    }
}
