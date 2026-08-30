<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;

final readonly class GetQuote
{
    public function __construct(private QuoteRepository $quotes) {}

    public function handle(QuoteId $id): QuoteView
    {
        return $this->quotes->get($id);
    }
}
