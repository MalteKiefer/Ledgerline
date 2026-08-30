<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class QuotePage
{
    /** @param list<QuoteView> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
