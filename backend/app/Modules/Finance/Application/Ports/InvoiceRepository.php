<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use Closure;
use DateTimeImmutable;

interface InvoiceRepository
{
    public function get(InvoiceId $id): InvoiceView;

    public function createDraft(InvoiceDraftData $data): InvoiceId;

    public function createDraftFromSource(InvoiceDraftSource $source, IdempotencyKey $key): InvoiceView;

    /**
     * @param  Closure(InvoiceView, int, string): InvoiceDraftSource  $buildSource
     */
    public function createCancellationDraft(
        InvoiceId $originalId,
        IdempotencyKey $key,
        Closure $buildSource,
    ): InvoiceId;

    public function updateDraft(InvoiceId $id, InvoiceDraftData $data, int $expectedVersion): InvoiceView;

    public function deleteDraft(InvoiceId $id, int $expectedVersion): void;

    public function finalize(InvoiceId $id, IdempotencyKey $key, Closure $publish): FinalizedInvoice;

    /**
     * @param  Closure(int, string): array{number: string, year: int, sequence: int}  $allocateNumber
     * @param  Closure(string, array<array-key, mixed>): StoredDocument  $storePdf
     * @param  Closure(int, string, array<int, int>, DateTimeImmutable): void  $recordInventory
     */
    public function finalizeAtomically(
        InvoiceId $id,
        IdempotencyKey $key,
        Closure $allocateNumber,
        Closure $storePdf,
        Closure $recordInventory,
    ): FinalizedInvoice;

    public function markDeliverySent(DeliveryId $deliveryId, DateTimeImmutable $at): InvoiceView;

    /** @return array{recipient:string, pdf_path:string, pdf_sha256:string} */
    public function assertDeliveryReady(InvoiceId $id, ?string $recipient, string $kind): array;

    /** @param array<string, int|string|bool|null> $context */
    public function replayDelivery(
        InvoiceId $id,
        string $kind,
        ?string $recipient,
        IdempotencyKey $key,
        array $context = [],
    ): ?DeliveryId;

    /**
     * @param  array<string, int|string|bool|null>  $context
     * @return array{DeliveryId, bool}
     */
    public function queueDelivery(
        InvoiceId $id,
        string $kind,
        string $recipient,
        IdempotencyKey $key,
        array $context = [],
        ?DateTimeImmutable $eligibilityAt = null,
    ): array;

    /** @return array{DeliveryId, bool} */
    public function retryDelivery(DeliveryId $failedDelivery, IdempotencyKey $key): array;

    /** @return array{invoice_id:int, kind:string, recipient:string, pdf_path:string, pdf_sha256:string} */
    public function assertDeliveryRetryReady(DeliveryId $failedDelivery): array;

    public function replayDeliveryRetry(DeliveryId $failedDelivery, IdempotencyKey $key): ?DeliveryId;

    public function deliveryNeedsDispatch(DeliveryId $delivery): bool;
}
