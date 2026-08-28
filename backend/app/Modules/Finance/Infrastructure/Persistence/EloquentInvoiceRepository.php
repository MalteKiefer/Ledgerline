<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class EloquentInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private readonly IdempotencyStore $idempotency,
        private readonly Clock $clock,
    ) {}

    public function get(InvoiceId $id): InvoiceView
    {
        return $this->view($this->ownedInvoice($id));
    }

    public function createDraft(InvoiceDraftData $data): InvoiceId
    {
        $ownerId = $this->ownerId();
        $calculated = $this->calculatedDraft($data);

        return DB::transaction(function () use ($ownerId, $data, $calculated): InvoiceId {
            $series = new DocumentSeriesRecord;
            $series->forceFill([
                'user_id' => $ownerId,
                'uuid' => (string) Str::uuid(),
                'document_type' => 'invoice',
                'status' => 'draft',
                'created_by' => $ownerId,
            ])->save();
            $revision = $series->revisions()->make([
                'status' => 'draft',
                'snapshot' => $calculated['snapshot'],
                'net_minor' => $calculated['net'],
                'vat_minor' => $calculated['vat'],
                'gross_minor' => $calculated['gross'],
                'currency' => $data->currency,
            ]);
            $revision->forceFill([
                'revision_number' => 1,
                'created_by' => $ownerId,
            ])->save();
            $invoice = new InvoiceRecord;
            $invoice->forceFill([
                'user_id' => $ownerId,
                'uuid' => (string) Str::uuid(),
                'document_series_id' => $series->id,
                'current_revision_id' => $revision->id,
                'kind' => 'invoice',
                'issue_date' => $data->issueDate->format('Y-m-d'),
                'due_date' => $data->dueDate->format('Y-m-d'),
                'partner_id' => $data->partnerId,
                'project_id' => $data->projectId,
                'workflow_status' => 'draft',
                'allocated_minor' => 0,
                'open_minor' => $calculated['gross'],
                'version' => 0,
            ])->save();

            return new InvoiceId((int) $invoice->id);
        }, 1);
    }

    public function updateDraft(InvoiceId $id, InvoiceDraftData $data, int $expectedVersion): InvoiceView
    {
        if ($expectedVersion < 0) {
            throw new \InvalidArgumentException('Expected invoice version must not be negative.');
        }
        $ownerId = $this->ownerId();
        $calculated = $this->calculatedDraft($data);

        return DB::transaction(function () use ($id, $data, $expectedVersion, $ownerId, $calculated): InvoiceView {
            $locator = $this->ownedInvoice($id);
            DocumentSeriesRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail(['id']);
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $invoice->workflow_status !== 'draft') {
                throw new DomainException('invoice_not_editable');
            }
            if ((int) $invoice->version !== $expectedVersion) {
                throw new DomainException('invoice_version_conflict');
            }

            $revision = $invoice->currentRevision()->lockForUpdate()->firstOrFail();
            $revision->forceFill([
                'snapshot' => $calculated['snapshot'],
                'net_minor' => $calculated['net'],
                'vat_minor' => $calculated['vat'],
                'gross_minor' => $calculated['gross'],
                'currency' => $data->currency,
            ])->save();
            $updated = DB::table('finance_invoices')
                ->where('id', $id->value)
                ->where('user_id', $ownerId)
                ->where('workflow_status', 'draft')
                ->where('version', $expectedVersion)
                ->update([
                    'issue_date' => $data->issueDate->format('Y-m-d'),
                    'due_date' => $data->dueDate->format('Y-m-d'),
                    'partner_id' => $data->partnerId,
                    'project_id' => $data->projectId,
                    'open_minor' => $calculated['gross'],
                    'version' => $expectedVersion + 1,
                    'updated_at' => $this->clock->now(),
                ]);

            if ($updated !== 1) {
                throw new DomainException('invoice_version_conflict');
            }

            return $this->view($invoice->refresh());
        }, 1);
    }

    public function finalize(InvoiceId $id, IdempotencyKey $key, Closure $publish): FinalizedInvoice
    {
        $requestHash = hash('sha256', "invoice.finalize:{$id->value}");

        return DB::transaction(function () use ($id, $key, $publish, $requestHash): FinalizedInvoice {
            $reservation = $this->idempotency->reserve('invoice.finalize', $key, $requestHash);

            if ($reservation['status'] === 'replay') {
                return $this->replayedFinalization($id, $reservation['response_payload']);
            }
            if ($reservation['status'] !== 'new') {
                throw new DomainException('idempotency_'.$reservation['status']);
            }

            $ownerId = $this->ownerId();
            $locator = $this->ownedInvoice($id);
            DocumentSeriesRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail(['id']);
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();
            $invoice->currentRevision()->lockForUpdate()->firstOrFail(['id']);
            $result = $publish($this->view($invoice));

            if (! $result instanceof FinalizedInvoice || $result->invoice->id->value !== $id->value) {
                throw new LogicException('Invoice finalization publisher returned an invalid result.');
            }

            $this->idempotency->complete($reservation['record_id'], 200, [
                'revision_id' => $result->revisionId,
                'pdf_path' => $result->pdfPath,
                'pdf_sha256' => $result->pdfSha256,
                'finalized_at' => $result->finalizedAt->format(DATE_ATOM),
            ]);

            return $result;
        }, 1);
    }

    public function markDeliverySent(DeliveryId $deliveryId, DateTimeImmutable $at): InvoiceView
    {
        $ownerId = $this->ownerId();

        return DB::transaction(function () use ($deliveryId, $at, $ownerId): InvoiceView {
            $locator = InvoiceDeliveryRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->findOrFail($deliveryId->value);
            DocumentSeriesRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail(['id']);
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();
            $invoice->currentRevision()->lockForUpdate()->firstOrFail(['id']);
            $delivery = InvoiceDeliveryRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($deliveryId->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $delivery->status !== 'sent') {
                $updated = DB::table('finance_invoice_deliveries')
                    ->where('id', $deliveryId->value)
                    ->where('user_id', $ownerId)
                    ->whereIn('status', ['pending', 'sending'])
                    ->update([
                        'status' => 'sent',
                        'last_attempt_at' => $at,
                        'sent_at' => $at,
                        'last_error_code' => null,
                        'next_retry_at' => null,
                        'updated_at' => $this->clock->now(),
                    ]);

                if ($updated !== 1) {
                    throw new DomainException('delivery_state_conflict');
                }

                DB::table('finance_invoices')
                    ->where('id', $invoice->id)
                    ->where('user_id', $ownerId)
                    ->whereIn('workflow_status', ['finalized', 'sent'])
                    ->update([
                        'workflow_status' => 'sent',
                        'sent_at' => $at,
                        'updated_at' => $this->clock->now(),
                    ]);
            }

            return $this->view($invoice->refresh());
        }, 1);
    }

    private function ownedInvoice(InvoiceId $id): InvoiceRecord
    {
        return InvoiceRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id->value);
    }

    private function ownerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Invoice persistence requires an authenticated owner.');
        }

        return (int) $ownerId;
    }

    private function view(InvoiceRecord $invoice): InvoiceView
    {
        $revision = $invoice->currentRevision()->firstOrFail();

        return new InvoiceView(
            new InvoiceId((int) $invoice->id),
            (string) $invoice->uuid,
            (string) $invoice->kind,
            is_string($invoice->number) ? $invoice->number : null,
            (string) $invoice->workflow_status,
            $this->date($invoice->getAttribute('issue_date')),
            $this->date($invoice->getAttribute('due_date')),
            (int) $revision->net_minor,
            (int) $revision->vat_minor,
            (int) $revision->gross_minor,
            (int) $invoice->allocated_minor,
            (int) $invoice->open_minor,
            (string) $revision->currency,
            (int) $invoice->version,
            $this->date($invoice->getAttribute('created_at')),
            $this->date($invoice->getAttribute('updated_at')),
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            throw new LogicException('Invoice persistence date metadata is incomplete.');
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    /** @return array{snapshot: array<string, mixed>, net: int, vat: int, gross: int} */
    private function calculatedDraft(InvoiceDraftData $data): array
    {
        $lines = array_map(
            static fn ($line): DocumentLine => new DocumentLine(
                $line->description,
                DecimalQuantity::fromString($line->quantity),
                Money::fromMinor($line->unitPriceMinor, $data->currency),
                $line->taxRateBasisPoints,
            ),
            $data->lines,
        );
        $totals = (new DocumentCalculator)->calculate($lines, $data->discount);

        if (($data->controlNetMinor !== null && $data->controlNetMinor !== $totals->net->minor())
            || ($data->controlVatMinor !== null && $data->controlVatMinor !== $totals->vat->minor())
            || ($data->controlGrossMinor !== null && $data->controlGrossMinor !== $totals->gross->minor())) {
            throw new DomainException('control_totals_mismatch');
        }

        $snapshotLines = [];
        foreach ($data->lines as $line) {
            $snapshotLines[] = [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unitPriceMinor,
                'tax_rate_basis_points' => $line->taxRateBasisPoints,
                'unit' => $line->unit,
                'product_id' => $line->productId,
                'kind' => $line->kind,
            ];
        }

        return [
            'snapshot' => [
                'customer' => $data->customer,
                'issue_date' => $data->issueDate->format('Y-m-d'),
                'due_date' => $data->dueDate->format('Y-m-d'),
                'currency' => $data->currency,
                'lines' => $snapshotLines,
                'totals' => [
                    'net_minor' => $totals->net->minor(),
                    'vat_minor' => $totals->vat->minor(),
                    'gross_minor' => $totals->gross->minor(),
                ],
            ],
            'net' => $totals->net->minor(),
            'vat' => $totals->vat->minor(),
            'gross' => $totals->gross->minor(),
        ];
    }

    /** @param array<string, mixed>|null $payload */
    private function replayedFinalization(InvoiceId $id, ?array $payload): FinalizedInvoice
    {
        if (! is_array($payload)
            || ! is_int($payload['revision_id'] ?? null)
            || ! is_string($payload['pdf_path'] ?? null)
            || ! is_string($payload['pdf_sha256'] ?? null)
            || ! is_string($payload['finalized_at'] ?? null)) {
            throw new LogicException('Stored invoice finalization result is incomplete.');
        }

        return new FinalizedInvoice(
            $this->get($id),
            $payload['revision_id'],
            $payload['pdf_path'],
            $payload['pdf_sha256'],
            new DateTimeImmutable($payload['finalized_at']),
        );
    }
}
