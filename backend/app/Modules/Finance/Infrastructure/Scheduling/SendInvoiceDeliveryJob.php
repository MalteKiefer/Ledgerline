<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Scheduling;

use App\Models\UserSetting;
use App\Modules\Finance\Infrastructure\Mail\CompanyInvoiceMailer;
use App\Modules\Finance\Infrastructure\Mail\InvoiceRevisionMail;
use App\Modules\Finance\Infrastructure\Mail\SafePreAcceptMailFailure;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

final class SendInvoiceDeliveryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $ownerId,
        public readonly int $deliveryId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->deliveryId;
    }

    public function handle(CompanyInvoiceMailer $mailer): void
    {
        $lease = Cache::lock('finance:invoice-delivery:'.$this->ownerId.':'.$this->deliveryId, 300);
        if (! $lease->get()) {
            return;
        }

        try {
            $this->handleWithLease($mailer);
        } finally {
            $lease->release();
        }
    }

    private function handleWithLease(CompanyInvoiceMailer $mailer): void
    {
        $attempt = $this->beginAttempt();
        if ($attempt === null) {
            return;
        }
        try {
            $disk = config('files.disk');
            $bytes = Storage::disk(is_string($disk) ? $disk : 'files')->get($attempt['pdf_path']);
            if (! is_string($bytes)
                || ! str_starts_with($bytes, '%PDF-')
                || ! hash_equals($attempt['pdf_sha256'], hash('sha256', $bytes))) {
                throw new LogicException('invoice_delivery_pdf_invalid');
            }
        } catch (Throwable) {
            $this->retrySafeFailure($attempt['revision_id'], 'pdf_unavailable');

            return;
        }
        try {
            $result = $mailer->send(
                $this->ownerId,
                $attempt['recipient'],
                new InvoiceRevisionMail(
                    $attempt['message_id'],
                    $attempt['number'],
                    $bytes,
                    $this->filename($attempt['number']),
                    $mailer->senderIdentity($this->ownerId),
                    $attempt['kind'] === 'reminder',
                    $attempt['customer'],
                    $attempt['days_overdue'],
                    $attempt['open_amount'],
                ),
            );
        } catch (SafePreAcceptMailFailure) {
            $this->retrySafeFailure($attempt['revision_id'], 'smtp_send_failed');

            return;
        } catch (Throwable) {
            $this->recordUncertain($attempt['revision_id']);

            return;
        }
        if (! $result->accepted) {
            $this->recordUncertain($attempt['revision_id']);

            return;
        }
        $this->markSent($attempt['revision_id']);
    }

    /** @return array{revision_id:int,recipient:string,message_id:string,pdf_path:string,pdf_sha256:string,number:string,kind:string,customer:string,days_overdue:int,open_amount:string}|null */
    private function beginAttempt(): ?array
    {
        return DB::transaction(function (): ?array {
            [$series, $invoice, $revision, $delivery] = $this->lockedRecords();
            $state = (string) $delivery->status;
            if (in_array($state, ['sent', 'unknown'], true)) {
                return null;
            }
            if ($state === 'sending') {
                $this->setUnknown($series, $revision, $delivery);

                return null;
            }
            if (! in_array($state, ['pending', 'failed'], true)) {
                throw new LogicException('invoice_delivery_state_invalid');
            }
            $kind = (string) $delivery->kind;
            if ((int) $invoice->current_revision_id !== (int) $revision->id
                || ($kind === 'invoice' && (string) $invoice->workflow_status !== 'finalized')) {
                $this->recordFailure($series, $revision, $delivery, 'invoice_revision_stale', true);

                return null;
            }
            if ($kind === 'reminder' && ! $this->reminderEligible($invoice)) {
                $this->recordFailure($series, $revision, $delivery, 'invoice_not_overdue', true);

                return null;
            }
            $snapshot = $revision->getAttribute('snapshot');
            $path = $revision->getAttribute('pdf_path');
            $sha256 = $revision->getAttribute('pdf_sha256');
            $number = is_array($snapshot) ? $snapshot['document_number'] ?? null : null;
            $customerData = is_array($snapshot) && is_array($snapshot['customer'] ?? null)
                ? $snapshot['customer']
                : [];
            $customer = is_string($customerData['name'] ?? null) ? $customerData['name'] : '';
            if (! is_string($number) || trim($number) === ''
                || ! is_string($path) || ! is_string($sha256)) {
                throw new LogicException('invoice_delivery_revision_invalid');
            }
            DB::table('finance_invoice_deliveries')
                ->where('id', $delivery->id)
                ->where('user_id', $this->ownerId)
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'sending',
                    'attempts' => (int) $delivery->attempts + 1,
                    'last_attempt_at' => now(),
                    'last_error_code' => null,
                    'next_retry_at' => null,
                    'updated_at' => now(),
                ]);

            return [
                'revision_id' => (int) $revision->id,
                'recipient' => (string) $delivery->recipient,
                'message_id' => (string) $delivery->message_id,
                'pdf_path' => $path,
                'pdf_sha256' => $sha256,
                'number' => $number,
                'kind' => $kind,
                'customer' => $customer,
                'days_overdue' => $kind === 'reminder' ? $this->daysOverdue($invoice->due_date) : 0,
                'open_amount' => $kind === 'reminder'
                    ? $this->formatMinor((int) $invoice->open_minor, (string) $revision->currency)
                    : '',
            ];
        }, 1);
    }

    private function markSent(int $revisionId): void
    {
        DB::transaction(function () use ($revisionId): void {
            [$series, $invoice, $revision, $delivery] = $this->lockedRecords();
            if ((int) $revision->id !== $revisionId || (string) $delivery->status !== 'sending') {
                throw new LogicException('invoice_delivery_completion_conflict');
            }
            $sentAt = now();
            $updated = DB::table('finance_invoice_deliveries')
                ->where('id', $delivery->id)
                ->where('user_id', $this->ownerId)
                ->where('status', 'sending')
                ->update([
                    'status' => 'sent',
                    'sent_at' => $sentAt,
                    'last_error_code' => null,
                    'next_retry_at' => null,
                    'updated_at' => $sentAt,
                ]);
            if ($updated !== 1) {
                throw new LogicException('invoice_delivery_completion_conflict');
            }
            $type = (string) $delivery->kind === 'invoice' ? 'invoice.sent' : 'invoice.reminder.sent';
            if ((string) $delivery->kind === 'invoice') {
                $invoiceUpdated = DB::table('finance_invoices')
                    ->where('id', $invoice->id)
                    ->where('user_id', $this->ownerId)
                    ->where('workflow_status', 'finalized')
                    ->update([
                        'workflow_status' => 'sent',
                        'sent_at' => $sentAt,
                        'updated_at' => $sentAt,
                    ]);
                if ($invoiceUpdated !== 1) {
                    throw new LogicException('invoice_delivery_invoice_conflict');
                }
                DB::table('finance_document_series')
                    ->where('id', $series->id)
                    ->where('user_id', $this->ownerId)
                    ->update(['status' => 'sent', 'updated_at' => $sentAt]);
            }
            $this->activity($series, $revision, $delivery, $type, $sentAt);
        }, 1);
    }

    private function retrySafeFailure(int $revisionId, string $code): void
    {
        $final = DB::transaction(function () use ($revisionId, $code): bool {
            [$series, , $revision, $delivery] = $this->lockedRecords();
            if ((int) $revision->id !== $revisionId || (string) $delivery->status !== 'sending') {
                throw new LogicException('invoice_delivery_failure_conflict');
            }
            $final = $this->attempts() >= $this->tries;
            $this->recordFailure($series, $revision, $delivery, $code, $final);

            return $final;
        }, 1);
        if (! $final) {
            throw new RuntimeException('invoice_delivery_failed');
        }
    }

    private function recordUncertain(int $revisionId): void
    {
        DB::transaction(function () use ($revisionId): void {
            [$series, , $revision, $delivery] = $this->lockedRecords();
            if ((int) $revision->id !== $revisionId || (string) $delivery->status !== 'sending') {
                throw new LogicException('invoice_delivery_uncertain_conflict');
            }
            $this->setUnknown($series, $revision, $delivery);
        }, 1);
    }

    private function recordFailure(
        DocumentSeriesRecord $series,
        DocumentRevisionRecord $revision,
        InvoiceDeliveryRecord $delivery,
        string $code,
        bool $final,
    ): void {
        $at = now();
        DB::table('finance_invoice_deliveries')
            ->where('id', $delivery->id)
            ->where('user_id', $this->ownerId)
            ->update([
                'status' => $final ? 'failed' : 'pending',
                'last_error_code' => $code,
                'next_retry_at' => $final ? null : $at->copy()->addSeconds($this->backoff()[min(2, max(0, (int) $delivery->attempts - 1))]),
                'updated_at' => $at,
            ]);
        if ($final) {
            $this->activity($series, $revision, $delivery, 'invoice.mail.failed', $at, $code);
        }
    }

    private function setUnknown(
        DocumentSeriesRecord $series,
        DocumentRevisionRecord $revision,
        InvoiceDeliveryRecord $delivery,
    ): void {
        $at = now();
        DB::table('finance_invoice_deliveries')
            ->where('id', $delivery->id)
            ->where('user_id', $this->ownerId)
            ->update([
                'status' => 'unknown',
                'last_error_code' => 'delivery_outcome_uncertain',
                'next_retry_at' => null,
                'updated_at' => $at,
            ]);
        $this->activity($series, $revision, $delivery, 'invoice.mail.uncertain', $at, 'delivery_outcome_uncertain');
    }

    private function activity(
        DocumentSeriesRecord $series,
        DocumentRevisionRecord $revision,
        InvoiceDeliveryRecord $delivery,
        string $type,
        mixed $at,
        ?string $errorCode = null,
    ): void {
        $parts = explode('@', (string) $delivery->recipient, 2);
        $payload = ['delivery_id' => (int) $delivery->id, 'recipient_domain' => $parts[1] ?? ''];
        if ($type === 'invoice.reminder.sent') {
            $payload['level'] = $this->reminderLevel((int) $series->id, (int) $delivery->id);
        }
        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }
        $activity = new DocumentActivityRecord;
        $activity->forceFill([
            'user_id' => $this->ownerId,
            'document_series_id' => $series->id,
            'document_revision_id' => $revision->id,
            'type' => $type,
            'payload' => $payload,
            'created_by' => $this->ownerId,
            'created_at' => $at,
        ])->save();
    }

    private function reminderLevel(int $seriesId, int $deliveryId): int
    {
        $payloads = DB::table('finance_document_activities')
            ->where('user_id', $this->ownerId)
            ->where('document_series_id', $seriesId)
            ->where('type', 'invoice.reminder.queued')
            ->pluck('payload');
        foreach ($payloads as $payload) {
            $decoded = is_string($payload) ? json_decode($payload, true) : null;
            if (is_array($decoded)
                && ($decoded['delivery_id'] ?? null) === $deliveryId
                && is_int($decoded['level'] ?? null)) {
                return $decoded['level'];
            }
        }

        throw new LogicException('invoice_reminder_level_missing');
    }

    /** @return array{DocumentSeriesRecord, InvoiceRecord, DocumentRevisionRecord, InvoiceDeliveryRecord} */
    private function lockedRecords(): array
    {
        $identity = InvoiceDeliveryRecord::query()->withoutGlobalScopes()
            ->where('user_id', $this->ownerId)->findOrFail($this->deliveryId, [
                'invoice_id', 'document_series_id', 'document_revision_id',
            ]);
        $series = DocumentSeriesRecord::query()->withoutGlobalScopes()
            ->where('user_id', $this->ownerId)->whereKey($identity->document_series_id)
            ->lockForUpdate()->firstOrFail();
        $invoice = InvoiceRecord::query()->withoutGlobalScopes()
            ->where('user_id', $this->ownerId)->whereKey($identity->invoice_id)
            ->lockForUpdate()->firstOrFail();
        $revision = DocumentRevisionRecord::query()->withoutGlobalScopes()
            ->where('user_id', $this->ownerId)->where('document_series_id', $series->id)
            ->whereKey($identity->document_revision_id)->lockForUpdate()->firstOrFail();
        $delivery = InvoiceDeliveryRecord::query()->withoutGlobalScopes()
            ->where('user_id', $this->ownerId)->whereKey($this->deliveryId)
            ->lockForUpdate()->firstOrFail();

        return [$series, $invoice, $revision, $delivery];
    }

    private function filename(string $number): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number);

        return (is_string($safe) && $safe !== '' ? $safe : 'invoice').'.pdf';
    }

    private function daysOverdue(mixed $dueDate): int
    {
        if (! $dueDate instanceof DateTimeInterface) {
            throw new LogicException('invoice_delivery_due_date_invalid');
        }
        $configured = UserSetting::query()->find($this->ownerId)?->getAttribute('timezone');
        $fallback = config('app.timezone', 'UTC');
        $name = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : (is_string($fallback) ? $fallback : 'UTC');
        try {
            $zone = new DateTimeZone($name);
        } catch (Throwable) {
            $zone = new DateTimeZone(is_string($fallback) ? $fallback : 'UTC');
        }
        $due = new DateTimeImmutable($dueDate->format('Y-m-d'), $zone);
        $today = (new DateTimeImmutable('now', $zone))->setTime(0, 0);

        return max(0, (int) $due->diff($today)->format('%a'));
    }

    private function reminderEligible(InvoiceRecord $invoice): bool
    {
        return (string) $invoice->workflow_status === 'sent'
            && (int) $invoice->open_minor > 0
            && $this->daysOverdue($invoice->due_date) > 0;
    }

    private function formatMinor(int $minor, string $currency): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT).' '.$currency;
    }
}
