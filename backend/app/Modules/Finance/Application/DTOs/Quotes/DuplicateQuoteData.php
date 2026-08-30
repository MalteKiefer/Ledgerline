<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class DuplicateQuoteData
{
    public function __construct(
        public QuoteId $sourceQuoteId,
        public int $expectedVersion,
        public ?int $sourceRevisionId,
        public string $idempotencyKey,
    ) {}
}
