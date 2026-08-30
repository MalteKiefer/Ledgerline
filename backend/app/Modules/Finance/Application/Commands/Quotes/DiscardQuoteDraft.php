<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use InvalidArgumentException;

final readonly class DiscardQuoteDraft
{
    public function __construct(private QuoteRepository $quotes) {}

    public function handle(QuoteId $id, int $expectedVersion): QuoteView
    {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected quote version must not be negative.');
        }

        return $this->quotes->discardDraft($id, $expectedVersion);
    }
}
