<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

final readonly class DocumentLine
{
    public function __construct(
        public string $description,
        public DecimalQuantity $quantity,
        public Money $unitPrice,
        public int $taxRateBasisPoints,
    ) {}
}
