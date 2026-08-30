<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuotePage;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;

final readonly class ListQuotes
{
    public function __construct(private QuoteRepository $quotes) {}

    /** @param array<string, mixed> $filters */
    public function handle(array $filters, int $page, int $perPage): QuotePage
    {
        return $this->quotes->page($filters, $page, $perPage);
    }
}
