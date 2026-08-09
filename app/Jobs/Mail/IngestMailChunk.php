<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Services\Mail\IngestStatus;
use App\Services\Mail\MaildirIngestor;
use App\Support\Mail\ImapDeleter;
use App\Support\Mail\MailLogger;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WORKER of the mail-archive sync pipeline: parse + archive one bounded slice of
 * Maildir message files for a folder, via the real MaildirIngestor.
 *
 * Loss-safety: MaildirIngestor is idempotent (content-hash dedup) and atomic
 * (blob before ledger, unlink only after commit; a failed file is left in
 * place), so a retry of this whole job is always safe and never duplicates.
 *
 * Two error regimes, both loss-safe:
 *   - Per-file: a parse/blob/ledger failure on one message is caught, logged and
 *     skipped so it never blocks the rest of the chunk. The file stays in the
 *     Maildir, so the next scheduled sync re-dispatches and retries it.
 *   - Whole-job: a Throwable escaping the loop lets the job retry with backoff;
 *     the dedup makes the retry a no-op for anything already stored.
 *
 * If the batch has been cancelled (user abort), the job returns immediately.
 *
 * Delete-after-import (opt-in per account): once a slice is durably archived,
 * the freshly-STORED messages' origin UIDs (from the Maildir filename) are
 * removed from the origin mailbox in ONE ImapDeleter session. It runs strictly
 * AFTER the archive commit (never before), and is best-effort — a delete
 * failure never fails the ingest or loses the archived copy.
 */
class IngestMailChunk implements ShouldQueue
{
    use Batchable;
    use Queueable;

    /** A whole-job retry is safe (idempotent ingestor); cap it so a genuinely
     *  stuck chunk fails loudly instead of retrying forever. */
    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  list<string>  $paths  absolute Maildir message-file paths in this slice
     */
    public function __construct(
        public int $accountId,
        public string $folder,
        public array $paths,
    ) {}

    /**
     * Exponential-ish backoff between whole-job retries (seconds).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(MaildirIngestor $ingestor, ImapDeleter $deleter): void
    {
        // User abort: a cancelled batch stops the rest of the ingest at once.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $account = MailAccount::find($this->accountId);
        if ($account === null) {
            return; // account deleted mid-run; nothing to archive to.
        }

        $summary = ['stored' => 0, 'duplicate' => 0, 'quarantined' => 0, 'skipped_old' => 0, 'skipped_spam' => 0, 'failed' => 0];
        /** @var list<string> $deleteUids Origin UIDs of freshly-stored messages (delete-after-import). */
        $deleteUids = [];

        foreach ($this->paths as $path) {
            // A prior attempt of this job (or a same-content dedup) may have
            // already archived + unlinked this file — skip vanished paths.
            if (! is_file($path)) {
                continue;
            }

            try {
                $result = $ingestor->ingestFile($account, $this->folder, $path);
                $summary[$result->status->value]++;
                if ($result->status === IngestStatus::Stored && $result->uid !== null) {
                    $deleteUids[] = $result->uid;
                }
            } catch (Throwable $e) {
                // Parse/blob/ledger failure: the ingestor left the file in place
                // (it only unlinks after commit), so nothing is lost — the next
                // sync retries it. Isolate it so one poison message never blocks
                // the rest of the chunk.
                $summary['failed']++;
                Log::warning('mail.chunk.ingest_failed', [
                    'account_id' => $this->accountId,
                    'folder' => $this->folder,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete-after-import (opt-in): remove the just-archived messages from
        // the origin, AFTER their archive commit. Best-effort — never fails the
        // ingest or loses the archived copy.
        if ($account->delete_after_import && $deleteUids !== []) {
            try {
                $removed = $deleter->deleteUids($account, $this->folder, $deleteUids, (string) $account->password);
                MailLogger::record($account, 'info', 'origin_deleted', $this->folder, "removed {$removed} origin message(s) after import");
            } catch (Throwable $e) {
                Log::warning('mail.chunk.delete_after_import_failed', [
                    'account_id' => $this->accountId,
                    'folder' => $this->folder,
                    'error' => $e->getMessage(),
                ]);
                MailLogger::record($account, 'warn', 'origin_delete_failed', $this->folder, 'origin delete-after-import failed');
            }
        }

        Log::info('mail.chunk.done', [
            'account_id' => $this->accountId,
            'folder' => $this->folder,
            'summary' => $summary,
        ]);

        // Per-folder ingest outcome for the owner-visible log.
        $level = $summary['failed'] > 0 ? 'warn' : 'info';
        MailLogger::record(
            $account,
            $level,
            'chunk_ingested',
            $this->folder,
            sprintf(
                'archived %d, duplicate %d, skipped_old %d, skipped_spam %d, failed %d, quarantined %d',
                $summary['stored'], $summary['duplicate'], $summary['skipped_old'], $summary['skipped_spam'],
                $summary['failed'], $summary['quarantined'],
            ),
        );
    }
}
