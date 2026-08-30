<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\DecideQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;

final readonly class AcceptQuote
{
    public function __construct(
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
    ) {}

    public function handle(DecideQuoteData $data): QuoteView
    {
        return $this->decide($data, 'accepted');
    }

    private function decide(DecideQuoteData $data, string $decision): QuoteView
    {
        $hash = hash('sha256', json_encode([
            'decision' => $decision,
            'expected_revision_id' => $data->expectedRevisionId,
            'expected_version' => $data->expectedVersion,
            'quote_uuid' => $data->quoteId->uuid,
        ], JSON_THROW_ON_ERROR));
        $operation = $this->operations->reserve(
            $data->quoteId->ownerId,
            'accept',
            $data->idempotencyKey,
            $hash,
            $data->quoteId,
        );
        if ($operation->status === 'failed') {
            throw new InvalidQuoteAction($operation->errorCode ?? 'quote_accept_failed');
        }
        if ($operation->status === 'replay') {
            return $this->quotes->get($data->quoteId);
        }

        return $this->quotes->decide(
            $data->quoteId,
            $data->expectedVersion,
            $data->expectedRevisionId,
            $decision,
            $operation->recordId,
        );
    }
}
