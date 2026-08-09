<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Services\Mail\MaildirIngestor;
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
 * NOTE: delete-after-import (removing the just-archived messages from the origin
 * server) is a Phase 4 feature and is deliberately NOT performed here yet — the
 * origin mailbox is only ever read.
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

    public function handle(MaildirIngestor $ingestor): void
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

        foreach ($this->paths as $path) {
            // A prior attempt of this job (or a same-content dedup) may have
            // already archived + unlinked this file — skip vanished paths.
            if (! is_file($path)) {
                continue;
            }

            try {
                $result = $ingestor->ingestFile($account, $this->folder, $path);
                $summary[$result->status->value]++;
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
