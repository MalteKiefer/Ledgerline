<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\Commands\CreateDocumentRevision;
use App\Modules\Finance\Application\Commands\PublishDocumentRevision;
use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\Quotes\OperationReservation;
use App\Modules\Finance\Application\DTOs\Quotes\PublishQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteNumberAllocator;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Quotes\QuoteNumber;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use LogicException;

final readonly class PublishQuote
{
    public function __construct(
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
        private QuoteNumberAllocator $numbers,
        private CreateDocumentRevision $createRevision,
        private PublishDocumentRevision $publishRevision,
    ) {}

    public function handle(PublishQuoteData $data): QuoteView
    {
        $requestSha256 = hash('sha256', json_encode([
            'change_reason' => $data->changeReason,
            'expected_version' => $data->expectedVersion,
            'quote_uuid' => $data->quoteId->uuid,
        ], JSON_THROW_ON_ERROR));
        $operation = $this->operations->reserve(
            $data->quoteId->ownerId,
            'publish',
            $data->idempotencyKey,
            $requestSha256,
            $data->quoteId,
        );

        if ($operation->status === 'replay') {
            return $this->replay($data, $operation);
        }
        if ($operation->status === 'failed') {
            throw new InvalidQuoteAction($operation->errorCode ?? 'quote_publish_failed');
        }

        $result = $operation->result ?? [];
        $revisionId = $this->checkpointRevisionId($result);

        if ($revisionId === null) {
            $candidate = $this->quotes->get($data->quoteId);
            $this->revisionData($candidate, $data->changeReason, 'VALIDATION-1');
            $prepared = $this->quotes->preparePublication(
                $data->quoteId,
                $data->expectedVersion,
                $operation->recordId,
                fn (string $issueDate): array => $this->numbers->allocate(
                    $data->quoteId->ownerId,
                    $issueDate,
                ),
            );
            $revisionId = $this->createRevision->handleIdempotently(
                $this->revisionData($prepared, $data->changeReason),
                'quote-publish-operation-'.$operation->recordId,
            );
            $operation = $this->operations->checkpoint($operation, [
                'revision_id' => $revisionId->value,
            ]);
        }

        $published = $this->publishRevision->handle($revisionId);
        $operation = $this->operations->checkpoint($operation, [
            'revision_id' => $revisionId->value,
            'pdf_path' => $published->path,
            'pdf_sha256' => $published->sha256,
        ]);
        $quote = $this->quotes->finalizePublication(
            $data->quoteId,
            $data->expectedVersion,
            $revisionId,
        );
        $this->operations->succeed($operation, [
            'quote_uuid' => $quote->id->uuid,
            'revision_id' => $revisionId->value,
            'pdf_path' => $published->path,
            'pdf_sha256' => $published->sha256,
        ]);

        return $quote;
    }

    private function replay(PublishQuoteData $data, OperationReservation $operation): QuoteView
    {
        $uuid = $operation->result['quote_uuid'] ?? null;
        $revisionId = $operation->result['revision_id'] ?? null;
        if (! is_string($uuid)
            || $uuid !== $data->quoteId->uuid
            || ! is_int($revisionId)
            || $revisionId < 1) {
            throw new LogicException('Completed quote publication has no replay identity.');
        }

        return $this->quotes->get($data->quoteId);
    }

    /** @param array<string, mixed> $result */
    private function checkpointRevisionId(array $result): ?DocumentRevisionId
    {
        $revisionId = $result['revision_id'] ?? null;

        return is_int($revisionId) && $revisionId > 0
            ? new DocumentRevisionId($revisionId)
            : null;
    }

    private function revisionData(
        QuoteView $quote,
        ?string $changeReason,
        ?string $validationNumber = null,
    ): CreateRevisionData {
        $draft = $quote->draft;
        if ($draft === null) {
            throw new InvalidQuoteAction('quote_draft_missing');
        }
        $documentNumber = $quote->number ?? $validationNumber;
        if ($documentNumber === null) {
            throw new LogicException('Prepared quote number is missing.');
        }

        $currency = $this->requiredString($draft, 'currency');
        $linesValue = $draft['lines'] ?? null;
        if (! is_array($linesValue) || $linesValue === []) {
            throw new LogicException('Quote draft lines are missing.');
        }
        $lines = [];
        foreach ($linesValue as $line) {
            if (! is_array($line)) {
                throw new LogicException('Quote draft line must be an array.');
            }
            $quantityScaled = $line['quantity_scaled'] ?? null;
            $unitPriceMinor = $line['unit_price_minor'] ?? null;
            $taxRateBasisPoints = $line['tax_rate_basis_points'] ?? null;
            if (! is_int($quantityScaled)
                || ! is_int($unitPriceMinor)
                || ! is_int($taxRateBasisPoints)) {
                throw new LogicException('Quote draft line amounts are missing.');
            }
            $lines[] = new DocumentLine(
                $this->requiredString($line, 'description'),
                DecimalQuantity::fromString($this->quantity($quantityScaled)),
                Money::fromMinor($unitPriceMinor, $currency),
                $taxRateBasisPoints,
            );
        }

        $discountValue = $draft['discount'] ?? null;
        if (! is_array($discountValue)) {
            throw new LogicException('Quote draft discount is missing.');
        }
        $discount = match ($discountValue['type'] ?? null) {
            'none' => Discount::none($currency),
            'percent' => isset($discountValue['basis_points']) && is_int($discountValue['basis_points'])
                ? Discount::percentBasisPoints($discountValue['basis_points'], $currency)
                : throw new LogicException('Quote percentage discount is missing basis points.'),
            'fixed' => isset($discountValue['minor']) && is_int($discountValue['minor'])
                ? Discount::fixed(Money::fromMinor($discountValue['minor'], $currency))
                : throw new LogicException('Quote fixed discount is missing minor units.'),
            default => throw new LogicException('Quote draft discount type is invalid.'),
        };
        $revisionNumber = ($quote->currentRevision?->revisionNumber ?? 0) + 1;
        $customer = $draft['customer'] ?? null;
        if (! is_array($customer)) {
            throw new LogicException('Quote draft customer is missing.');
        }

        return new CreateRevisionData(
            seriesUuid: $quote->id->uuid,
            snapshot: [
                'schema_version' => 1,
                'document_type' => 'quote',
                'series_uuid' => $quote->id->uuid,
                'document_number' => $documentNumber,
                'revision_number' => $revisionNumber,
                'revision_label' => (new QuoteNumber($documentNumber))->revisionLabel($revisionNumber),
                'title' => $this->requiredString($draft, 'title'),
                'customer' => $customer,
                'partner_id' => $quote->partnerId,
                'issue_date' => $this->requiredString($draft, 'issue_date'),
                'valid_until' => $this->requiredString($draft, 'valid_until'),
                'currency' => $currency,
                'lines' => $linesValue,
                'discount' => $discountValue,
                'totals' => $draft['totals'] ?? [],
                'intro_text' => $this->nullableString($draft, 'intro_text'),
                'outro_text' => $this->nullableString($draft, 'outro_text'),
                'customer_note' => $this->nullableString($draft, 'customer_note'),
            ],
            lines: $lines,
            discount: $discount,
            changeReason: $changeReason,
        );
    }

    /** @param array<array-key, mixed> $value */
    private function requiredString(array $value, string $key): string
    {
        $item = $value[$key] ?? null;
        if (! is_string($item) || trim($item) === '') {
            throw new LogicException("Quote draft {$key} is missing.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private function nullableString(array $value, string $key): ?string
    {
        $item = $value[$key] ?? null;
        if ($item !== null && ! is_string($item)) {
            throw new LogicException("Quote draft {$key} must be a string or null.");
        }

        return $item;
    }

    private function quantity(int $scaled): string
    {
        $negative = $scaled < 0;
        $digits = ltrim((string) $scaled, '-');
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -4);
        $fraction = rtrim(substr($digits, -4), '0');
        $quantity = $fraction === '' ? $whole : $whole.'.'.$fraction;

        return $negative ? '-'.$quantity : $quantity;
    }
}
