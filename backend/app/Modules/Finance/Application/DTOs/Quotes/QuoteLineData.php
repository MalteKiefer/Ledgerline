<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class QuoteLineData
{
    public function __construct(
        public string $description,
        public string $quantity,
        public string $unit,
        public string $unitPrice,
        public string $taxRate,
        public string $kind,
        public ?int $productId,
    ) {}
}
