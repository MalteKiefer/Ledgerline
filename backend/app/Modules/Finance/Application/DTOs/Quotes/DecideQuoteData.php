<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class DecideQuoteData
{
    public function __construct(
        public QuoteId $quoteId,
        public int $expectedVersion,
        public int $expectedRevisionId,
        public string $idempotencyKey,
    ) {}
}
