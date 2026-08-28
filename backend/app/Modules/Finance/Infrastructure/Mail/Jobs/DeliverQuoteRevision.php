<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail\Jobs;

use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Modules\Finance\Infrastructure\Mail\QuoteRevisionMail;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

final class DeliverQuoteRevision implements ShouldBeUnique, ShouldQueue
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
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->deliveryId;
    }

    public function handle(CompanySmtpMailer $smtp): void
    {
        $attempt = $this->beginAttempt();
        if ($attempt === null) {
            return;
        }

        try {
            $diskName = config('files.disk');
            $disk = Storage::disk(is_string($diskName) ? $diskName : 'files');
            $bytes = $disk->get($attempt['pdf_path']);
            if (! is_string($bytes)
                || ! str_starts_with($bytes, '%PDF-')
                || ! hash_equals($attempt['pdf_sha256'], hash('sha256', $bytes))) {
                throw new LogicException('quote_delivery_pdf_invalid');
            }

            $smtp->send(
                $this->ownerId,
                $attempt['recipient'],
                new QuoteRevisionMail(
                    $attempt['message_id'],
                    $attempt['number'],
                    $attempt['revision_label'],
                    $attempt['valid_until'],
                    $bytes,
                    $this->filename($attempt['revision_label']),
                    $smtp->senderIdentity($this->ownerId),
                ),
            );
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof LogicException
                && $exception->getMessage() === 'quote_delivery_pdf_invalid'
                ? 'pdf_unavailable'
                : 'smtp_send_failed';
            $final = $this->attempts() >= $this->tries;
            $this->recordFailure($attempt['revision_id'], $errorCode, $final);

            if (! $final) {
                throw new RuntimeException('quote_delivery_failed');
            }

            return;
        }
        $this->markSent($attempt['revision_id']);
    }

    /**
     * @return array{
     *   revision_id: int, recipient: string, message_id: string, pdf_path: string,
     *   pdf_sha256: string, number: string, revision_label: string, valid_until: string
     * }|null
     */
    private function beginAttempt(): ?array
    {
        return DB::transaction(function (): ?array {
            [$series, $quote, $revision, $delivery] = $this->lockedRecords();
            if ((string) $delivery->state === 'sent') {
                return null;
            }
            if ((string) $delivery->state === 'sending') {
                $failedAt = now();
                DB::table('finance_quote_deliveries')
                    ->where('id', $delivery->id)
                    ->where('user_id', $this->ownerId)
                    ->where('state', 'sending')
                    ->update([
                        'state' => 'failed',
                        'last_error_code' => 'delivery_outcome_uncertain',
                        'failed_at' => $failedAt,
                    ]);
                $this->activity(
                    $series,
                    $revision,
                    $delivery,
                    'quote.mail.uncertain',
                    $failedAt,
                    'delivery_outcome_uncertain',
                );

                return null;
            }
            if (! in_array((string) $delivery->state, ['queued', 'failed'], true)) {
                throw new LogicException('quote_delivery_state_invalid');
            }
            if ((int) $quote->current_revision_id !== (int) $revision->id
                || (string) $series->status !== 'sent'
                || (string) $revision->status !== 'published'
                || $revision->published_at === null) {
                $failedAt = now();
                DB::table('finance_quote_deliveries')
                    ->where('id', $delivery->id)
                    ->where('user_id', $this->ownerId)
                    ->update([
                        'state' => 'failed',
                        'last_error_code' => 'quote_revision_stale',
                        'failed_at' => $failedAt,
                    ]);
                $this->activity(
                    $series,
                    $revision,
                    $delivery,
                    'quote.mail.failed',
                    $failedAt,
                    'quote_revision_stale',
                );

                return null;
            }
            $snapshot = $revision->getAttribute('snapshot');
            if (! is_array($snapshot)) {
                throw new LogicException('quote_delivery_snapshot_invalid');
            }
            $pdfPath = $revision->getAttribute('pdf_path');
            $pdfSha256 = $revision->getAttribute('pdf_sha256');
            if (! is_string($pdfPath)
                || preg_match('#\Afinance/revisions/[0-9a-f]{2}/[0-9a-f]{64}\.pdf\z#D', $pdfPath) !== 1
                || ! is_string($pdfSha256)
                || preg_match('/\A[0-9a-f]{64}\z/D', $pdfSha256) !== 1) {
                throw new LogicException('quote_delivery_pdf_invalid');
            }

            DB::table('finance_quote_deliveries')
                ->where('id', $delivery->id)
                ->where('user_id', $this->ownerId)
                ->update([
                    'state' => 'sending',
                    'attempts' => (int) $delivery->attempts + 1,
                    'last_error_code' => null,
                    'failed_at' => null,
                ]);

            return [
                'revision_id' => (int) $revision->id,
                'recipient' => (string) $delivery->recipient,
                'message_id' => (string) $delivery->message_id,
                'pdf_path' => $pdfPath,
                'pdf_sha256' => $pdfSha256,
                'number' => $this->snapshotString($snapshot, 'document_number'),
                'revision_label' => $this->snapshotString($snapshot, 'revision_label'),
                'valid_until' => $this->snapshotString($snapshot, 'valid_until'),
            ];
        }, 1);
    }

    private function markSent(int $revisionId): void
    {
        DB::transaction(function () use ($revisionId): void {
            [$series, , $revision, $delivery] = $this->lockedRecords();
            if ((int) $revision->id !== $revisionId || (string) $delivery->state !== 'sending') {
                throw new LogicException('quote_delivery_completion_conflict');
            }
            $sentAt = now();
            DB::table('finance_quote_deliveries')
                ->where('id', $delivery->id)
                ->where('user_id', $this->ownerId)
                ->where('state', 'sending')
                ->update([
                    'state' => 'sent',
                    'last_error_code' => null,
                    'sent_at' => $sentAt,
                    'failed_at' => null,
                ]);
            $this->activity($series, $revision, $delivery, 'quote.mail.sent', $sentAt);
        }, 1);
    }

    private function recordFailure(int $revisionId, string $errorCode, bool $final): void
    {
        DB::transaction(function () use ($revisionId, $errorCode, $final): void {
            [$series, , $revision, $delivery] = $this->lockedRecords();
            if ((int) $revision->id !== $revisionId || (string) $delivery->state !== 'sending') {
                throw new LogicException('quote_delivery_failure_conflict');
            }
            $failedAt = now();
            DB::table('finance_quote_deliveries')
                ->where('id', $delivery->id)
                ->where('user_id', $this->ownerId)
                ->where('state', 'sending')
                ->update([
                    'state' => $final ? 'failed' : 'queued',
                    'last_error_code' => $errorCode,
                    'failed_at' => $final ? $failedAt : null,
                ]);
            if ($final) {
                $this->activity(
                    $series,
                    $revision,
                    $delivery,
                    'quote.mail.failed',
                    $failedAt,
                    $errorCode,
                );
            }
        }, 1);
    }

    private function activity(
        DocumentSeriesRecord $series,
        DocumentRevisionRecord $revision,
        QuoteDeliveryRecord $delivery,
        string $type,
        mixed $createdAt,
        ?string $errorCode = null,
    ): void {
        $payload = [
            'delivery_id' => (int) $delivery->id,
            'recipient_domain' => (string) $delivery->recipient_domain,
        ];
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
            'created_at' => $createdAt,
        ]);
        $activity->save();
    }

    /** @return array{DocumentSeriesRecord, QuoteSeriesRecord, DocumentRevisionRecord, QuoteDeliveryRecord} */
    private function lockedRecords(): array
    {
        $deliveryIdentity = QuoteDeliveryRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_deliveries.user_id', $this->ownerId)
            ->whereKey($this->deliveryId)
            ->firstOrFail(['document_series_id', 'document_revision_id']);
        $series = DocumentSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_series.user_id', $this->ownerId)
            ->where('document_type', 'quote')
            ->whereKey($deliveryIdentity->document_series_id)
            ->lockForUpdate()
            ->firstOrFail();
        $quote = QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_series.user_id', $this->ownerId)
            ->where('document_series_id', $series->id)
            ->lockForUpdate()
            ->firstOrFail();
        $revision = DocumentRevisionRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_revisions.user_id', $this->ownerId)
            ->where('document_series_id', $series->id)
            ->whereKey($deliveryIdentity->document_revision_id)
            ->lockForUpdate()
            ->firstOrFail();
        $delivery = QuoteDeliveryRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_deliveries.user_id', $this->ownerId)
            ->where('document_series_id', $series->id)
            ->where('document_revision_id', $revision->id)
            ->whereKey($this->deliveryId)
            ->lockForUpdate()
            ->firstOrFail();

        return [$series, $quote, $revision, $delivery];
    }

    /** @param array<array-key, mixed> $snapshot */
    private function snapshotString(array $snapshot, string $key): string
    {
        $value = $snapshot[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new LogicException('quote_delivery_snapshot_invalid');
        }

        return $value;
    }

    private function filename(string $revisionLabel): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revisionLabel);

        return (is_string($safe) && $safe !== '' ? $safe : 'quote').'.pdf';
    }
}
