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
}
