<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\DuplicateQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Services\Quotes\QuoteDraftFactory;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use LogicException;

final readonly class DuplicateQuote
{
    public function __construct(
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
        private QuoteDraftFactory $drafts,
    ) {}

    public function handle(DuplicateQuoteData $data): QuoteView
    {
        $hash = hash('sha256', json_encode([
            'expected_version' => $data->expectedVersion,
            'source_quote_uuid' => $data->sourceQuoteId->uuid,
            'source_revision_id' => $data->sourceRevisionId,
        ], JSON_THROW_ON_ERROR));
        $operation = $this->operations->reserve(
            $data->sourceQuoteId->ownerId,
            'duplicate',
            $data->idempotencyKey,
            $hash,
            $data->sourceQuoteId,
        );
        if ($operation->status === 'failed') {
            throw new InvalidQuoteAction($operation->errorCode ?? 'quote_duplicate_failed');
        }
        if ($operation->status === 'replay') {
            $uuid = $operation->result['quote_uuid'] ?? null;
            if (! is_string($uuid)) {
                throw new LogicException('Completed quote duplication has no replay identity.');
            }

            return $this->quotes->get(new QuoteId(
                $data->sourceQuoteId->ownerId,
                $uuid,
            ));
        }

        return $this->quotes->duplicate(
            $data->sourceQuoteId,
            $data->expectedVersion,
            $data->sourceRevisionId,
            $operation->recordId,
            fn (array $snapshot, ?int $partnerId): array => $this->drafts->build(
                $data->sourceQuoteId->ownerId,
                $this->draftData($snapshot, $partnerId),
            ),
        );
    }

    /** @param array<array-key, mixed> $snapshot */
    private function draftData(array $snapshot, ?int $partnerId): QuoteDraftData
    {
        $customer = $snapshot['customer'] ?? null;
        $sourceLines = $snapshot['lines'] ?? null;
        $discount = $snapshot['discount'] ?? null;
        if (! is_array($customer) || ! is_array($sourceLines) || ! is_array($discount)) {
            throw new LogicException('Quote source snapshot is incomplete.');
        }
        $lines = [];
        foreach ($sourceLines as $sourceLine) {
            if (! is_array($sourceLine)) {
                throw new LogicException('Quote source line is invalid.');
            }
            $lines[] = new QuoteLineData(
                $this->requiredString($sourceLine, 'description'),
                $this->requiredString($sourceLine, 'quantity'),
                $this->requiredString($sourceLine, 'unit'),
                $this->requiredString($sourceLine, 'unit_price'),
                $this->requiredString($sourceLine, 'tax_rate'),
                $this->requiredString($sourceLine, 'kind'),
                isset($sourceLine['product_id']) && is_int($sourceLine['product_id'])
                    ? $sourceLine['product_id']
                    : null,
            );
        }

        return new QuoteDraftData(
            $this->requiredString($snapshot, 'title'),
            $partnerId,
            $this->customer($customer),
            null,
            null,
            $this->requiredString($snapshot, 'currency'),
            $lines,
            $this->requiredString($discount, 'type'),
            isset($discount['value']) && is_string($discount['value']) ? $discount['value'] : null,
            $this->nullableString($snapshot, 'intro_text'),
            $this->nullableString($snapshot, 'outro_text'),
            $this->nullableString($snapshot, 'internal_note')
                ?? $this->nullableString($snapshot, 'customer_note'),
        );
    }

    /** @param array<array-key, mixed> $value */
    private function requiredString(array $value, string $key): string
    {
        $item = $value[$key] ?? null;
        if (! is_string($item) || trim($item) === '') {
            throw new LogicException("Quote source {$key} is missing.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private function nullableString(array $value, string $key): ?string
    {
        $item = $value[$key] ?? null;

        return is_string($item) ? $item : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function customer(array $value): array
    {
        $customer = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Quote source customer must use string keys.');
            }
            $customer[$key] = $item;
        }

        return $customer;
    }
}
