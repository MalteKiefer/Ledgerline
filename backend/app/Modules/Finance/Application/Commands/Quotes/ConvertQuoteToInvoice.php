<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\ConvertQuoteToInvoiceData;
use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteToInvoicePort;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use LogicException;

final readonly class ConvertQuoteToInvoice
{
    public function __construct(
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
        private QuoteToInvoicePort $target,
    ) {}

    public function handle(ConvertQuoteToInvoiceData $data): InvoiceDraftTarget
    {
        $hash = hash('sha256', json_encode([
            'expected_revision_id' => $data->expectedRevisionId,
            'expected_version' => $data->expectedVersion,
            'quote_uuid' => $data->quoteId->uuid,
            'target_type' => 'invoice',
        ], JSON_THROW_ON_ERROR));
        $operation = $this->operations->reserve(
            $data->quoteId->ownerId,
            'convert_invoice',
            $data->idempotencyKey,
            $hash,
            $data->quoteId,
        );
        if ($operation->status === 'failed') {
            throw new InvalidQuoteAction($operation->errorCode ?? 'quote_conversion_failed');
        }
        if ($operation->status === 'replay') {
            return $this->targetFromResult($operation->result);
        }

        return $this->quotes->convertToInvoice(
            $data->quoteId,
            $data->expectedVersion,
            $data->expectedRevisionId,
            $operation->recordId,
            fn ($source, array $snapshot): InvoiceDraftTarget => $this->target->createDraft(
                $data->quoteId->ownerId,
                $source,
                $snapshot,
            ),
        );
    }

    /** @param array<string, mixed>|null $result */
    private function targetFromResult(?array $result): InvoiceDraftTarget
    {
        $reference = $result['target_reference'] ?? null;
        $id = $result['target_id'] ?? null;
        if (! is_string($reference) || ($id !== null && ! is_int($id))) {
            throw new LogicException('Completed quote conversion has no replay target.');
        }

        return new InvoiceDraftTarget($reference, $id);
    }
}
