<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;

final readonly class StartQuoteVersion
{
    public function __construct(private QuoteRepository $quotes) {}

    public function handle(QuoteId $id, int $expectedVersion): QuoteView
    {
        return $this->quotes->startVersion($id, $expectedVersion);
    }
}
