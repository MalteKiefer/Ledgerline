<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\FinanceProject;
use App\Models\UserSetting;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            return $this->persistDraft($ownerId, $data, $calculated);
        }, 1);
    }

    public function createDraftFromSource(InvoiceDraftSource $source, IdempotencyKey $key): InvoiceView
    {
        $ownerId = $this->ownerId();
        $requestHash = $this->sourceRequestHash($source);

        return DB::transaction(function () use ($ownerId, $source, $key, $requestHash): InvoiceView {
            $calculated = $this->calculatedDraft($source->draft, $source, $requestHash);
            $reservation = $this->idempotency->reserve(
                'invoice.create_from_source',
                $key,
                $requestHash,
            );
            if ($reservation['status'] === 'replay') {
                $invoiceId = $reservation['response_payload']['invoice_id'] ?? null;
                if (! is_int($invoiceId)) {
                    throw new LogicException('Stored source invoice result is incomplete.');
                }

                return $this->get(new InvoiceId($invoiceId));
            }
            if ($reservation['status'] !== 'new') {
                throw new DomainException('idempotency_'.$reservation['status']);
            }

            DB::table('users')->where('id', $ownerId)->lockForUpdate()->first();
            $existing = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('source_type', $source->sourceType)
                ->where('source_key', $source->sourceKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof InvoiceRecord) {
                $existingSnapshot = $this->snapshot($existing->currentRevision()
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->getAttribute('snapshot'));
                $storedRequestHash = is_array($existingSnapshot['source'] ?? null)
                    ? $existingSnapshot['source']['request_sha256'] ?? null
                    : null;
                if ((int) $existing->source_revision_id !== $source->sourceRevisionId
                    || ! hash_equals(
                        (string) $existing->source_snapshot_sha256,
                        $source->sourceSnapshotSha256,
                    )
                    || ! is_string($storedRequestHash)
                    || ! hash_equals($storedRequestHash, $requestHash)) {
                    throw new DomainException('source_snapshot_conflict');
                }

                $invoiceId = (int) $existing->id;
            } else {
                $invoiceId = $this->persistDraft(
                    $ownerId,
                    $source->draft,
                    $calculated,
                    $source,
                )->value;
            }

            $this->idempotency->complete($reservation['record_id'], 201, [
                'invoice_id' => $invoiceId,
            ]);

            return $this->get(new InvoiceId($invoiceId));
        }, 1);
    }

    public function updateDraft(InvoiceId $id, InvoiceDraftData $data, int $expectedVersion): InvoiceView
    {
        if ($expectedVersion < 0) {
            throw new \InvalidArgumentException('Expected invoice version must not be negative.');
        }
        $ownerId = $this->ownerId();

        return DB::transaction(function () use ($id, $data, $expectedVersion, $ownerId): InvoiceView {
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
            $this->assertOwnedReferences($ownerId, $data);

            $revision = $invoice->currentRevision()->lockForUpdate()->firstOrFail();
            $calculated = $this->calculatedDraft($data);
            $currentSnapshot = $this->snapshot($revision->getAttribute('snapshot'));
            if ($invoice->source_type !== null) {
                $sourceSnapshot = is_array($currentSnapshot['source'] ?? null)
                    ? $currentSnapshot['source']
                    : null;
                if (! is_array($sourceSnapshot)) {
                    throw new LogicException('Source invoice snapshot metadata is incomplete.');
                }
                $calculated['snapshot']['source'] = $sourceSnapshot;
            }
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
            $this->appendActivity(
                $ownerId,
                (int) $invoice->document_series_id,
                (int) $revision->id,
                'invoice.draft.updated',
                $expectedVersion + 1,
            );

            return $this->view($invoice->refresh());
        }, 1);
    }

    public function deleteDraft(InvoiceId $id, int $expectedVersion): void
    {
        if ($expectedVersion < 0) {
            throw new \InvalidArgumentException('Expected invoice version must not be negative.');
        }
        $ownerId = $this->ownerId();

        DB::transaction(function () use ($id, $expectedVersion, $ownerId): void {
            $locator = $this->ownedInvoice($id);
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail();
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();
            $revision = $invoice->currentRevision()->lockForUpdate()->firstOrFail();

            if ((string) $invoice->workflow_status !== 'draft') {
                throw new DomainException('invoice_not_deletable');
            }
            if ($invoice->source_type !== null) {
                throw new DomainException('source_invoice_not_deletable');
            }
            if ((int) $invoice->version !== $expectedVersion) {
                throw new DomainException('invoice_version_conflict');
            }

            $deleted = DB::table('finance_invoices')
                ->where('id', $id->value)
                ->where('user_id', $ownerId)
                ->where('workflow_status', 'draft')
                ->where('version', $expectedVersion)
                ->delete();
            if ($deleted !== 1) {
                throw new DomainException('invoice_version_conflict');
            }

            DB::table('finance_document_activities')
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->delete();
            $revisionDeleted = DB::table('finance_document_revisions')
                ->where('id', $revision->id)
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->where('status', 'draft')
                ->whereNull('published_at')
                ->delete();
            $seriesDeleted = DB::table('finance_document_series')
                ->where('id', $series->id)
                ->where('user_id', $ownerId)
                ->where('document_type', 'invoice')
                ->where('status', 'draft')
                ->delete();

            if ($revisionDeleted !== 1 || $seriesDeleted !== 1) {
                throw new DomainException('invoice_delete_conflict');
            }
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

    public function finalizeAtomically(
        InvoiceId $id,
        IdempotencyKey $key,
        Closure $allocateNumber,
        Closure $storePdf,
        Closure $recordInventory,
    ): FinalizedInvoice {
        $requestHash = hash('sha256', "invoice.finalize:{$id->value}");

        return DB::transaction(function () use (
            $id,
            $key,
            $allocateNumber,
            $storePdf,
            $recordInventory,
            $requestHash,
        ): FinalizedInvoice {
            $reservation = $this->idempotency->reserve('invoice.finalize', $key, $requestHash);

            if ($reservation['status'] === 'replay') {
                return $this->replayedFinalization($id, $reservation['response_payload']);
            }
            if ($reservation['status'] !== 'new') {
                throw new DomainException('idempotency_'.$reservation['status']);
            }

            $ownerId = $this->ownerId();
            $locator = $this->ownedInvoice($id);
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('document_type', 'invoice')
                ->whereKey($locator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail();
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();
            $revision = DocumentRevisionRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($invoice->current_revision_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $series->status !== 'draft'
                || (string) $invoice->workflow_status !== 'draft'
                || $invoice->number !== null
                || (string) $revision->status !== 'draft'
                || $revision->published_at !== null) {
                throw new DomainException('invoice_not_finalizable');
            }

            $prepared = $this->preparedFinalization($ownerId, $series, $invoice, $revision);
            $number = $allocateNumber(
                $ownerId,
                $this->date($invoice->getAttribute('issue_date'))->format('Y-m-d'),
            );
            if (! is_string($number['number'] ?? null)
                || trim($number['number']) === ''
                || ! is_int($number['year'] ?? null)
                || $number['year'] < 1
                || ! is_int($number['sequence'] ?? null)
                || $number['sequence'] < 1) {
                throw new LogicException('Invoice number allocator returned an invalid result.');
            }

            $snapshot = $prepared['snapshot'];
            $snapshot['document_number'] = $number['number'];
            $snapshot = $this->canonicalize($snapshot);
            $revision->forceFill([
                'snapshot' => $snapshot,
                'net_minor' => $prepared['net'],
                'vat_minor' => $prepared['vat'],
                'gross_minor' => $prepared['gross'],
                'currency' => $prepared['currency'],
            ])->save();

            $stored = $storePdf((string) $series->uuid, $snapshot);
            if (! $stored instanceof StoredDocument) {
                throw new LogicException('Invoice PDF storage returned an invalid result.');
            }
            $finalizedAt = $this->clock->now();
            $published = DB::table('finance_document_revisions')
                ->where('id', $revision->id)
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->where('status', 'draft')
                ->whereNull('published_at')
                ->update([
                    'status' => 'published',
                    'pdf_path' => $stored->path,
                    'pdf_sha256' => $stored->sha256,
                    'published_at' => $finalizedAt,
                ]);
            if ($published !== 1) {
                throw new DomainException('invoice_revision_publish_conflict');
            }

            $recordInventory(
                $ownerId,
                (string) $invoice->uuid,
                $prepared['inventory'],
                $finalizedAt,
            );

            $seriesUpdated = DB::table('finance_document_series')
                ->where('id', $series->id)
                ->where('user_id', $ownerId)
                ->where('document_type', 'invoice')
                ->where('status', 'draft')
                ->update([
                    'status' => 'finalized',
                    'updated_at' => $finalizedAt,
                ]);
            $invoiceUpdated = DB::table('finance_invoices')
                ->where('id', $invoice->id)
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->where('current_revision_id', $revision->id)
                ->where('workflow_status', 'draft')
                ->whereNull('number')
                ->where('version', (int) $invoice->version)
                ->update([
                    'number' => $number['number'],
                    'year' => $number['year'],
                    'sequence' => $number['sequence'],
                    'workflow_status' => 'finalized',
                    'finalized_at' => $finalizedAt,
                    'open_minor' => $prepared['gross'],
                    'version' => (int) $invoice->version + 1,
                    'updated_at' => $finalizedAt,
                ]);
            if ($seriesUpdated !== 1 || $invoiceUpdated !== 1) {
                throw new DomainException('invoice_finalization_conflict');
            }

            $publishedActivity = new DocumentActivityRecord;
            $publishedActivity->forceFill([
                'user_id' => $ownerId,
                'document_series_id' => $series->id,
                'document_revision_id' => $revision->id,
                'type' => 'revision.published',
                'payload' => [
                    'path' => $stored->path,
                    'pdf_sha256' => $stored->sha256,
                ],
                'created_by' => $ownerId,
                'created_at' => $finalizedAt,
            ])->save();
            $this->appendActivity(
                $ownerId,
                (int) $series->id,
                (int) $revision->id,
                'invoice.finalized',
                (int) $invoice->version + 1,
            );

            $this->idempotency->complete($reservation['record_id'], 200, [
                'revision_id' => (int) $revision->id,
                'pdf_path' => $stored->path,
                'pdf_sha256' => $stored->sha256,
                'finalized_at' => $finalizedAt->format(DATE_ATOM),
            ]);

            return new FinalizedInvoice(
                $this->view($invoice->refresh()),
                (int) $revision->id,
                $stored->path,
                $stored->sha256,
                $finalizedAt,
            );
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
            if (! in_array((string) $invoice->workflow_status, ['finalized', 'sent'], true)) {
                throw new DomainException('delivery_invoice_not_eligible');
            }
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

                $invoiceUpdated = DB::table('finance_invoices')
                    ->where('id', $invoice->id)
                    ->where('user_id', $ownerId)
                    ->whereIn('workflow_status', ['finalized', 'sent'])
                    ->update([
                        'workflow_status' => 'sent',
                        'sent_at' => $at,
                        'updated_at' => $this->clock->now(),
                    ]);

                if ($invoiceUpdated !== 1) {
                    throw new DomainException('delivery_invoice_state_conflict');
                }
            }

            return $this->view($invoice->refresh());
        }, 1);
    }

    public function assertDeliveryReady(InvoiceId $id, ?string $recipient, string $kind): array
    {
        if (! in_array($kind, ['invoice', 'reminder'], true)) {
            throw new DomainException('delivery_kind_invalid');
        }
        $invoice = $this->ownedInvoice($id);
        $allowed = $kind === 'invoice' ? ['finalized'] : ['sent'];
        if (! in_array((string) $invoice->workflow_status, $allowed, true)) {
            throw new DomainException('delivery_invoice_not_eligible');
        }
        $revision = DocumentRevisionRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->where('document_series_id', $invoice->document_series_id)
            ->whereKey($invoice->current_revision_id)
            ->firstOrFail();
        $path = $revision->getAttribute('pdf_path');
        $sha256 = $revision->getAttribute('pdf_sha256');
        if (! is_string($path)
            || preg_match('#\Afinance/revisions/[0-9a-f]{2}/[0-9a-f]{64}\.pdf\z#D', $path) !== 1
            || ! is_string($sha256)
            || preg_match('/\A[0-9a-f]{64}\z/D', $sha256) !== 1) {
            throw new DomainException('delivery_pdf_unavailable');
        }
        if ($recipient === null) {
            $snapshot = $revision->getAttribute('snapshot');
            $customer = is_array($snapshot) && is_array($snapshot['customer'] ?? null)
                ? $snapshot['customer']
                : [];
            $recipient = is_string($customer['email'] ?? null) ? $customer['email'] : null;
        }
        $recipient = is_string($recipient) ? trim($recipient) : '';
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('delivery_recipient_missing');
        }

        return [
            'recipient' => $recipient,
            'pdf_path' => $path,
            'pdf_sha256' => $sha256,
        ];
    }

    /** @param array<string, int|string|bool|null> $context */
    public function queueDelivery(
        InvoiceId $id,
        string $kind,
        string $recipient,
        IdempotencyKey $key,
        array $context = [],
    ): array {
        $ownerId = $this->ownerId();
        $keyHash = $key->hash();

        return DB::transaction(function () use ($id, $kind, $recipient, $keyHash, $ownerId, $context): array {
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();
            $revision = DocumentRevisionRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('document_series_id', $invoice->document_series_id)
                ->whereKey($invoice->current_revision_id)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = hash('sha256', json_encode([
                'invoice_id' => (int) $invoice->id,
                'revision_id' => (int) $revision->id,
                'kind' => $kind,
                'recipient' => $recipient,
                'context' => $this->canonicalize($context),
            ], JSON_THROW_ON_ERROR));
            $existing = InvoiceDeliveryRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('kind', $kind)
                ->where('idempotency_key_hash', $keyHash)
                ->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new DomainException('delivery_idempotency_conflict');
                }

                return [new DeliveryId((int) $existing->id, (string) $existing->uuid), false];
            }
            $normalized = $this->assertDeliveryReady($id, $recipient, $kind);
            if ($normalized['recipient'] !== $recipient) {
                throw new LogicException('Invoice delivery recipient changed while queuing.');
            }
            $uuid = (string) Str::uuid();
            $now = $this->clock->now();
            $deliveryId = DB::table('finance_invoice_deliveries')->insertGetId([
                'user_id' => $ownerId,
                'uuid' => $uuid,
                'invoice_id' => $invoice->id,
                'document_series_id' => $invoice->document_series_id,
                'document_revision_id' => $revision->id,
                'kind' => $kind,
                'recipient' => $recipient,
                'message_id' => '<'.$uuid.'@invoices.ledgerline>',
                'status' => 'pending',
                'attempts' => 0,
                'last_error_code' => null,
                'idempotency_key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'queued_at' => $now,
                'last_attempt_at' => null,
                'sent_at' => null,
                'next_retry_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($kind === 'reminder') {
                $activity = new DocumentActivityRecord;
                $activity->forceFill([
                    'user_id' => $ownerId,
                    'document_series_id' => $invoice->document_series_id,
                    'document_revision_id' => $revision->id,
                    'type' => 'invoice.reminder.queued',
                    'payload' => [
                        'delivery_id' => $deliveryId,
                        'level' => $context['level'] ?? null,
                    ],
                    'created_by' => $ownerId,
                    'created_at' => $now,
                ])->save();
            }

            return [new DeliveryId($deliveryId, $uuid), true];
        }, 1);
    }

    public function retryDelivery(DeliveryId $failedDelivery, IdempotencyKey $key): array
    {
        $ownerId = $this->ownerId();
        $keyHash = $key->hash();

        return DB::transaction(function () use ($failedDelivery, $keyHash, $ownerId): array {
            $source = InvoiceDeliveryRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($failedDelivery->value)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array((string) $source->status, ['failed', 'unknown'], true)) {
                throw new DomainException('delivery_retry_not_allowed');
            }
            $invoice = InvoiceRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($source->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $invoice->current_revision_id !== (int) $source->document_revision_id) {
                throw new DomainException('delivery_revision_stale');
            }
            if ((string) $source->kind === 'reminder') {
                $this->assertReminderOverdue($invoice);
            }
            $candidate = $this->assertDeliveryReady(
                new InvoiceId((int) $invoice->id),
                (string) $source->recipient,
                (string) $source->kind,
            );
            $recipient = $candidate['recipient'];
            $requestHash = hash('sha256', json_encode([
                'retry_of' => (int) $source->id,
                'invoice_id' => (int) $invoice->id,
                'revision_id' => (int) $source->document_revision_id,
                'kind' => (string) $source->kind,
                'recipient' => $recipient,
            ], JSON_THROW_ON_ERROR));
            $existing = InvoiceDeliveryRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('kind', $source->kind)
                ->where('idempotency_key_hash', $keyHash)
                ->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new DomainException('delivery_idempotency_conflict');
                }

                return [new DeliveryId((int) $existing->id, (string) $existing->uuid), false];
            }
            $uuid = (string) Str::uuid();
            $now = $this->clock->now();
            $id = DB::table('finance_invoice_deliveries')->insertGetId([
                'user_id' => $ownerId,
                'uuid' => $uuid,
                'invoice_id' => $source->invoice_id,
                'document_series_id' => $source->document_series_id,
                'document_revision_id' => $source->document_revision_id,
                'kind' => $source->kind,
                'recipient' => $recipient,
                'message_id' => '<'.$uuid.'@invoices.ledgerline>',
                'status' => 'pending',
                'attempts' => 0,
                'last_error_code' => null,
                'idempotency_key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'queued_at' => $now,
                'last_attempt_at' => null,
                'sent_at' => null,
                'next_retry_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [new DeliveryId($id, $uuid), true];
        }, 1);
    }

    public function assertDeliveryRetryReady(DeliveryId $failedDelivery): array
    {
        $ownerId = $this->ownerId();
        $source = InvoiceDeliveryRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey($failedDelivery->value)
            ->firstOrFail();
        if (! in_array((string) $source->status, ['failed', 'unknown'], true)) {
            throw new DomainException('delivery_retry_not_allowed');
        }
        $invoice = InvoiceRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey($source->invoice_id)
            ->firstOrFail();
        if ((int) $invoice->current_revision_id !== (int) $source->document_revision_id) {
            throw new DomainException('delivery_revision_stale');
        }
        if ((string) $source->kind === 'reminder') {
            $this->assertReminderOverdue($invoice);
        }
        $candidate = $this->assertDeliveryReady(
            new InvoiceId((int) $invoice->id),
            (string) $source->recipient,
            (string) $source->kind,
        );

        return [
            'invoice_id' => (int) $invoice->id,
            'kind' => (string) $source->kind,
            ...$candidate,
        ];
    }

    private function assertReminderOverdue(InvoiceRecord $invoice): void
    {
        $configured = UserSetting::query()->find($this->ownerId())?->getAttribute('timezone');
        $fallback = config('app.timezone', 'UTC');
        $name = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : (is_string($fallback) ? $fallback : 'UTC');
        try {
            $zone = new DateTimeZone($name);
        } catch (\Throwable) {
            $zone = new DateTimeZone(is_string($fallback) ? $fallback : 'UTC');
        }
        $dueDate = new DateTimeImmutable(
            $this->date($invoice->getAttribute('due_date'))->format('Y-m-d'),
            $zone,
        );
        $today = (new DateTimeImmutable('now', $zone))->setTime(0, 0);
        if ((string) $invoice->workflow_status !== 'sent'
            || (int) $invoice->open_minor <= 0
            || $dueDate >= $today) {
            throw new DomainException('invoice_not_overdue');
        }
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

    private function assertOwnedReferences(int $ownerId, InvoiceDraftData $data): void
    {
        if ($data->partnerId !== null && ! DB::table('finance_partners')
            ->where('id', $data->partnerId)
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(FinancePartner::class, [$data->partnerId]);
        }

        if ($data->projectId !== null && ! DB::table('finance_projects')
            ->where('id', $data->projectId)
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(FinanceProject::class, [$data->projectId]);
        }

        $productIds = array_values(array_unique(array_filter(
            array_map(static fn ($line): ?int => $line->productId, $data->lines),
            static fn (?int $productId): bool => $productId !== null,
        )));
        if ($productIds !== [] && DB::table('finance_products')
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->whereIn('id', $productIds)
            ->count() !== count($productIds)) {
            throw (new ModelNotFoundException)->setModel(FinanceProduct::class, $productIds);
        }
    }

    private function view(InvoiceRecord $invoice): InvoiceView
    {
        $revision = $invoice->currentRevision()->firstOrFail();
        $snapshot = $this->snapshot($revision->getAttribute('snapshot'));

        return new InvoiceView(
            new InvoiceId((int) $invoice->id),
            (string) $invoice->uuid,
            (string) $invoice->kind,
            is_string($invoice->number) ? $invoice->number : null,
            (string) $invoice->workflow_status,
            $this->date($invoice->getAttribute('issue_date')),
            $this->date($invoice->getAttribute('due_date')),
            $invoice->partner_id !== null ? (int) $invoice->partner_id : null,
            $invoice->project_id !== null ? (int) $invoice->project_id : null,
            (int) $revision->net_minor,
            (int) $revision->vat_minor,
            (int) $revision->gross_minor,
            (int) $invoice->allocated_minor,
            (int) $invoice->open_minor,
            (string) $revision->currency,
            (int) $invoice->version,
            $this->date($invoice->getAttribute('created_at')),
            $this->date($invoice->getAttribute('updated_at')),
            $snapshot,
            is_string($invoice->source_type) ? $invoice->source_type : null,
            is_string($invoice->source_key) ? $invoice->source_key : null,
            $invoice->source_revision_id !== null ? (int) $invoice->source_revision_id : null,
            is_string($invoice->source_snapshot_sha256) ? $invoice->source_snapshot_sha256 : null,
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            throw new LogicException('Invoice persistence date metadata is incomplete.');
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    /** @return array<string, mixed> */
    private function snapshot(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('Invoice revision snapshot is incomplete.');
        }

        $snapshot = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Invoice revision snapshot must be a JSON object.');
            }
            $snapshot[$key] = $item;
        }

        return $snapshot;
    }

    /** @return array{snapshot: array<string, mixed>, net: int, vat: int, gross: int} */
    private function calculatedDraft(
        InvoiceDraftData $data,
        ?InvoiceDraftSource $source = null,
        ?string $sourceRequestHash = null,
    ): array {
        $lines = [];
        $snapshotLines = [];
        foreach ($data->lines as $line) {
            $quantity = DecimalQuantity::fromString($line->quantity);
            $lines[] = new DocumentLine(
                $line->description,
                $quantity,
                Money::fromMinor($line->unitPriceMinor, $data->currency),
                $line->taxRateBasisPoints,
            );
            $snapshotLines[] = [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'quantity_scaled' => $quantity->scaled(),
                'unit_price_minor' => $line->unitPriceMinor,
                'tax_rate_basis_points' => $line->taxRateBasisPoints,
                'unit' => $line->unit,
                'product_id' => $line->productId,
                'kind' => $line->kind,
            ];
        }
        $totals = (new DocumentCalculator)->calculate($lines, $data->discount);

        if (($data->controlNetMinor !== null && $data->controlNetMinor !== $totals->net->minor())
            || ($data->controlVatMinor !== null && $data->controlVatMinor !== $totals->vat->minor())
            || ($data->controlGrossMinor !== null && $data->controlGrossMinor !== $totals->gross->minor())) {
            throw new DomainException('document_totals_mismatch');
        }

        $snapshot = [
            'customer' => $this->canonicalize($data->customer),
            'issue_date' => $data->issueDate->format('Y-m-d'),
            'due_date' => $data->dueDate->format('Y-m-d'),
            'currency' => $data->currency,
            'lines' => $snapshotLines,
            'discount' => [
                'basis_points' => $data->discount->basisPoints(),
                'fixed_minor' => $data->discount->fixedMinor(),
                'currency' => $data->discount->currency(),
            ],
            'totals' => [
                'net_minor' => $totals->net->minor(),
                'vat_minor' => $totals->vat->minor(),
                'gross_minor' => $totals->gross->minor(),
                'discount_minor' => $totals->discount->minor(),
                'currency' => $data->currency,
                'tax_breakdowns' => array_map(
                    static fn ($breakdown): array => [
                        'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                        'net_minor' => $breakdown->net->minor(),
                        'vat_minor' => $breakdown->vat->minor(),
                        'gross_minor' => $breakdown->gross->minor(),
                    ],
                    $totals->taxBreakdowns,
                ),
            ],
        ];
        if ($source !== null) {
            if (! is_string($sourceRequestHash)) {
                throw new LogicException('Source invoice request hash is missing.');
            }
            $snapshot['source'] = [
                'type' => $source->sourceType,
                'key' => $source->sourceKey,
                'revision_id' => $source->sourceRevisionId,
                'snapshot_sha256' => $source->sourceSnapshotSha256,
                'request_sha256' => $sourceRequestHash,
            ];
        }

        return [
            'snapshot' => $snapshot,
            'net' => $totals->net->minor(),
            'vat' => $totals->vat->minor(),
            'gross' => $totals->gross->minor(),
        ];
    }

    /**
     * @return array{
     *     snapshot: array<array-key, mixed>,
     *     net: int,
     *     vat: int,
     *     gross: int,
     *     currency: string,
     *     inventory: array<int, int>
     * }
     */
    private function preparedFinalization(
        int $ownerId,
        DocumentSeriesRecord $series,
        InvoiceRecord $invoice,
        DocumentRevisionRecord $revision,
    ): array {
        $draftSnapshot = $this->snapshot($revision->getAttribute('snapshot'));
        $currency = $draftSnapshot['currency'] ?? null;
        $customer = $draftSnapshot['customer'] ?? null;
        $lineValues = $draftSnapshot['lines'] ?? null;
        if (! is_string($currency)
            || ! is_array($customer)
            || ! is_array($lineValues)
            || $lineValues === []) {
            throw new DomainException('invoice_draft_snapshot_invalid');
        }

        $lines = [];
        foreach ($lineValues as $line) {
            if (! is_array($line)
                || ! is_string($line['description'] ?? null)
                || ! is_string($line['quantity'] ?? null)
                || ! is_int($line['unit_price_minor'] ?? null)
                || ! is_int($line['tax_rate_basis_points'] ?? null)
                || ! is_string($line['unit'] ?? null)) {
                throw new DomainException('invoice_draft_snapshot_invalid');
            }
            $productId = $line['product_id'] ?? null;
            $kind = $line['kind'] ?? null;
            if ($productId !== null && ! is_int($productId)) {
                throw new DomainException('invoice_draft_snapshot_invalid');
            }
            if ($kind !== null && ! is_string($kind)) {
                throw new DomainException('invoice_draft_snapshot_invalid');
            }
            $lines[] = new InvoiceLineData(
                $line['description'],
                $line['quantity'],
                $line['unit_price_minor'],
                $line['tax_rate_basis_points'],
                $line['unit'],
                $productId,
                $kind,
            );
        }

        $customerData = [];
        foreach ($customer as $key => $value) {
            if (! is_string($key)) {
                throw new DomainException('invoice_draft_snapshot_invalid');
            }
            $customerData[$key] = $value;
        }
        $data = new InvoiceDraftData(
            issueDate: $this->date($invoice->getAttribute('issue_date')),
            dueDate: $this->date($invoice->getAttribute('due_date')),
            currency: $currency,
            customer: $customerData,
            lines: $lines,
            discount: $this->snapshotDiscount($draftSnapshot, $currency),
            partnerId: $invoice->partner_id !== null ? (int) $invoice->partner_id : null,
            projectId: $invoice->project_id !== null ? (int) $invoice->project_id : null,
        );
        $calculated = $this->calculatedDraft($data);
        $source = $draftSnapshot['source'] ?? null;
        $this->assertFinalizationSource($invoice, $source);

        $snapshot = $calculated['snapshot'];
        $snapshot['schema_version'] = 1;
        $snapshot['document_type'] = 'invoice';
        $snapshot['invoice_kind'] = (string) $invoice->kind;
        $snapshot['series_uuid'] = (string) $series->uuid;
        $snapshot['document_number'] = null;
        $snapshot['revision_number'] = (int) $revision->revision_number;
        $snapshot['company'] = $this->companySnapshot($ownerId);
        if (is_array($source)) {
            $snapshot['source'] = $source;
        }
        $inventory = $this->authoritativeInventory($ownerId, $snapshot);

        return [
            'snapshot' => $this->canonicalize($inventory['snapshot']),
            'net' => $calculated['net'],
            'vat' => $calculated['vat'],
            'gross' => $calculated['gross'],
            'currency' => $currency,
            'inventory' => $inventory['quantities'],
        ];
    }

    /** @param array<array-key, mixed> $snapshot */
    private function snapshotDiscount(array $snapshot, string $currency): Discount
    {
        $discount = $snapshot['discount'] ?? null;
        if (! is_array($discount)
            || ! is_int($discount['basis_points'] ?? null)
            || ! is_int($discount['fixed_minor'] ?? null)
            || ($discount['currency'] ?? null) !== $currency) {
            throw new DomainException('invoice_draft_snapshot_invalid');
        }
        $basisPoints = $discount['basis_points'];
        $fixedMinor = $discount['fixed_minor'];
        if ($basisPoints !== 0 && $fixedMinor !== 0) {
            throw new DomainException('invoice_draft_snapshot_invalid');
        }

        if ($basisPoints !== 0) {
            return Discount::percentBasisPoints($basisPoints, $currency);
        }

        return $fixedMinor !== 0
            ? Discount::fixed(Money::fromMinor($fixedMinor, $currency))
            : Discount::none($currency);
    }

    private function assertFinalizationSource(InvoiceRecord $invoice, mixed $source): void
    {
        if ($invoice->source_type === null) {
            if ($source !== null) {
                throw new DomainException('source_snapshot_conflict');
            }

            return;
        }
        if (! is_array($source)
            || ($source['type'] ?? null) !== $invoice->source_type
            || ($source['key'] ?? null) !== $invoice->source_key
            || ($source['revision_id'] ?? null) !== (int) $invoice->source_revision_id
            || ($source['snapshot_sha256'] ?? null) !== $invoice->source_snapshot_sha256
            || ! is_string($source['request_sha256'] ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $source['request_sha256']) !== 1) {
            throw new DomainException('source_snapshot_conflict');
        }
    }

    /** @return array<string, mixed> */
    private function companySnapshot(int $ownerId): array
    {
        $settings = UserSetting::query()->where('user_id', $ownerId)->lockForUpdate()->first();

        $contacts = $settings?->getAttribute('company_contacts');

        return [
            'name' => $this->settingString($settings, 'company_name'),
            'address' => $this->settingString($settings, 'company_address'),
            'email' => $this->settingString($settings, 'company_email'),
            'phone' => $this->settingString($settings, 'company_phone'),
            'tax_id' => $this->settingString($settings, 'company_tax_id'),
            'vat_id' => $this->settingString($settings, 'company_vat_id'),
            'iban' => $this->settingString($settings, 'company_iban'),
            'bic' => $this->settingString($settings, 'company_bic'),
            'bank_name' => $this->settingString($settings, 'company_bank_name'),
            'website' => $this->settingString($settings, 'company_website'),
            'contacts' => is_array($contacts)
                ? $this->canonicalize($contacts)
                : [],
        ];
    }

    private function settingString(?UserSetting $settings, string $attribute): ?string
    {
        $value = $settings?->getAttribute($attribute);

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $snapshot
     * @return array{snapshot: array<array-key, mixed>, quantities: array<int, int>}
     */
    private function authoritativeInventory(int $ownerId, array $snapshot): array
    {
        $lines = $snapshot['lines'] ?? null;
        if (! is_array($lines)) {
            throw new DomainException('invoice_draft_snapshot_invalid');
        }

        $productIds = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new DomainException('invoice_draft_snapshot_invalid');
            }
            $productId = $line['product_id'] ?? null;
            if ($productId === null) {
                if (($line['kind'] ?? null) === 'hardware') {
                    throw new DomainException('invoice_inventory_line_invalid');
                }

                continue;
            }
            if (! is_int($productId) || $productId < 1) {
                throw new DomainException('invoice_inventory_line_invalid');
            }
            $productIds[$productId] = $productId;
        }
        ksort($productIds, SORT_NUMERIC);

        $products = [];
        if ($productIds !== []) {
            $rows = DB::table('finance_products')
                ->select(['id', 'kind'])
                ->where('user_id', $ownerId)
                ->whereNull('deleted_at')
                ->whereIn('id', array_values($productIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($rows as $row) {
                if (! is_numeric($row->id ?? null) || ! is_string($row->kind ?? null)) {
                    throw new DomainException('invoice_inventory_product_invalid');
                }
                $products[(int) $row->id] = $row->kind;
            }
            if (count($products) !== count($productIds)) {
                $missing = array_values(array_diff(array_keys($productIds), array_keys($products)));

                throw (new ModelNotFoundException)->setModel(FinanceProduct::class, $missing);
            }
        }

        $quantities = [];
        foreach ($lines as $index => $line) {
            $productId = $line['product_id'] ?? null;
            if ($productId === null) {
                continue;
            }
            if (! is_int($productId) || $productId < 1) {
                throw new DomainException('invoice_inventory_line_invalid');
            }
            $authoritativeKind = $products[$productId] ?? null;
            if (! in_array($authoritativeKind, ['service', 'hardware'], true)) {
                throw new DomainException('invoice_inventory_product_invalid');
            }
            $snapshotKind = $line['kind'] ?? null;
            if ($snapshotKind !== null && $snapshotKind !== $authoritativeKind) {
                throw new DomainException('invoice_inventory_kind_mismatch');
            }
            $lines[$index]['kind'] = $authoritativeKind;
            if ($authoritativeKind !== 'hardware') {
                continue;
            }
            $quantityScaled = $line['quantity_scaled'] ?? null;
            if (! is_int($quantityScaled)) {
                throw new DomainException('invoice_inventory_line_invalid');
            }
            $current = $quantities[$productId] ?? 0;
            if (($quantityScaled > 0 && $current > PHP_INT_MAX - $quantityScaled)
                || ($quantityScaled < 0 && $current < PHP_INT_MIN - $quantityScaled)) {
                throw new DomainException('invoice_inventory_quantity_invalid');
            }
            $next = $current + $quantityScaled;
            if ($next === 0) {
                unset($quantities[$productId]);

                continue;
            }
            $quantities[$productId] = $next;
        }
        ksort($quantities, SORT_NUMERIC);
        $snapshot['lines'] = $lines;

        return ['snapshot' => $snapshot, 'quantities' => $quantities];
    }

    /**
     * @param  array{snapshot: array<string, mixed>, net: int, vat: int, gross: int}  $calculated
     */
    private function persistDraft(
        int $ownerId,
        InvoiceDraftData $data,
        array $calculated,
        ?InvoiceDraftSource $source = null,
    ): InvoiceId {
        $this->assertOwnedReferences($ownerId, $data);
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
            'source_type' => $source?->sourceType,
            'source_key' => $source?->sourceKey,
            'source_revision_id' => $source?->sourceRevisionId,
            'source_snapshot_sha256' => $source?->sourceSnapshotSha256,
            'workflow_status' => 'draft',
            'allocated_minor' => 0,
            'open_minor' => $calculated['gross'],
            'version' => 0,
        ])->save();
        $this->appendActivity(
            $ownerId,
            (int) $series->id,
            (int) $revision->id,
            'invoice.draft.created',
            0,
        );

        return new InvoiceId((int) $invoice->id);
    }

    private function sourceRequestHash(InvoiceDraftSource $source): string
    {
        return hash('sha256', json_encode([
            'source_type' => $source->sourceType,
            'source_key' => $source->sourceKey,
            'source_revision_id' => $source->sourceRevisionId,
            'source_snapshot_sha256' => $source->sourceSnapshotSha256,
            'draft' => [
                'issue_date' => $source->draft->issueDate->format('Y-m-d'),
                'due_date' => $source->draft->dueDate->format('Y-m-d'),
                'currency' => $source->draft->currency,
                'customer' => $this->canonicalize($source->draft->customer),
                'lines' => array_map(static fn ($line): array => [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->unitPriceMinor,
                    'tax_rate_basis_points' => $line->taxRateBasisPoints,
                    'unit' => $line->unit,
                    'product_id' => $line->productId,
                    'kind' => $line->kind,
                ], $source->draft->lines),
                'discount_basis_points' => $source->draft->discount->basisPoints(),
                'discount_fixed_minor' => $source->draft->discount->fixedMinor(),
                'partner_id' => $source->draft->partnerId,
                'project_id' => $source->draft->projectId,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }

    private function appendActivity(
        int $ownerId,
        int $seriesId,
        int $revisionId,
        string $type,
        int $version,
    ): void {
        $activity = new DocumentActivityRecord;
        $activity->forceFill([
            'user_id' => $ownerId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => $type,
            'payload' => ['version' => $version],
            'created_by' => $ownerId,
            'created_at' => $this->clock->now(),
        ])->save();
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
