<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;

final readonly class ListQuoteRevisions
{
    public function __construct(private QuoteRepository $quotes) {}

    /** @return list<QuoteRevisionRef> */
    public function handle(QuoteId $id): array
    {
        return $this->quotes->revisions($id);
    }
}
