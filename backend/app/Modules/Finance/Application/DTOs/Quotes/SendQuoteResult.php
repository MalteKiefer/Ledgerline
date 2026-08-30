<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class SendQuoteResult
{
    public function __construct(
        public QuoteView $quote,
        public bool $replayed,
    ) {}
}
