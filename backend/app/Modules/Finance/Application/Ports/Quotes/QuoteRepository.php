<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuotePage;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Domain\Shared\DocumentTotals;

interface QuoteRepository
{
    public function get(QuoteId $id): QuoteView;

    /** @param array<string, mixed> $filters */
    public function page(array $filters, int $page, int $perPage): QuotePage;

    /** @return list<QuoteRevisionRef> */
    public function revisions(QuoteId $id): array;

    /** @param array<array-key, mixed> $payload */
    public function createDraft(
        int $ownerId,
        array $payload,
        DocumentTotals $totals,
        ?int $partnerId = null,
    ): QuoteView;

    /** @param array<array-key, mixed> $payload */
    public function updateDraft(
        QuoteId $id,
        int $expectedVersion,
        array $payload,
        DocumentTotals $totals,
        ?int $partnerId = null,
    ): QuoteView;

    public function discardDraft(QuoteId $id, int $expectedVersion): QuoteView;
}
