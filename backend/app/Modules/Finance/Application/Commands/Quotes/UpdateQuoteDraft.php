<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Services\Quotes\QuoteDraftFactory;
use InvalidArgumentException;

final readonly class UpdateQuoteDraft
{
    public function __construct(
        private QuoteDraftFactory $factory,
        private QuoteRepository $quotes,
    ) {}

    public function handle(QuoteId $id, int $expectedVersion, QuoteDraftData $data): QuoteView
    {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected quote version must not be negative.');
        }

        $draft = $this->factory->build($id->ownerId, $data);

        return $this->quotes->updateDraft(
            $id,
            $expectedVersion,
            $draft['payload'],
            $draft['totals'],
            $draft['partner_id'],
        );
    }
}
