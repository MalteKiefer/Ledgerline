<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Services\Mail\MaildirIngestor;
use App\Services\Mail\MbsyncRunner;
use App\Support\Mail\MailLogger;
use App\Support\Redactor;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * PRODUCER of the mail-archive sync pipeline (Task 7): fetch one account's
 * mailbox, then fan the fetched Maildir out to chunked ingest workers.
 *
 * Flow (per account):
 *   0. Identity-key pre-flight: MaildirIngestor::ownerIdentity() must resolve
 *      BOTH the owner's published X25519 + ML-KEM public keys before mbsync
 *      is ever invoked. A message MaildirIngestor cannot seal is left as
 *      durable plaintext in the Maildir until the owner's keys exist — and
 *      every scheduled sync would re-fetch/re-check it, so an owner who has
 *      configured a mailbox but never unlocked their vault would otherwise
 *      accumulate indefinitely-unsealed plaintext on disk. Skipping the fetch
 *      entirely (no mbsync run at all) keeps that window at zero: the server
 *      never holds plaintext it cannot immediately seal. The account is
 *      marked 'error' with a non-secret, actionable last_error; the next
 *      scheduled sync re-checks and archives normally once keys are published.
 *   1. status = syncing.
 *   2. MbsyncRunner::run() — a pull-only, read-only-origin mbsync mirror into
 *      the account's durable Maildir scratch tree.
 *   3. Anything but Success (Failed / HostRejected / Unavailable) → mark the
 *      account errored with a redacted message and STOP — no ingest. The
 *      account can't sync; that IS an account-level fault to surface, so even
 *      the runner's "Unavailable = environmental" case is recorded here as an
 *      error at the orchestration layer, per the task brief.
 *   4. On Success, PAGE the Maildir (folders × their cur/+new/ files) into
 *      IngestMailChunk jobs of a bounded size and dispatch them as a batch.
 *      The producer never enqueues one job per message: a 100k-message mailbox
 *      becomes ~1k chunk jobs, not 100k.
 *   5. The batch's finally() returns the account to idle + stamps last_synced_at.
 *
 * RESUMABILITY is anchored in DURABLE STATE, never in this batch: mbsync's own
 * UID/UIDVALIDITY state files decide what to re-fetch, and the ledger's
 * content-hash dedup makes every re-ingest a no-op. A crashed worker, an
 * aborted batch, or a lost job_batches row costs at most a redundant re-sync —
 * never a lost or duplicated message. Whatever fails to ingest this run stays
 * in the Maildir and is retried on the next scheduled sync.
 *
 * BACKFILL: MailAccount::backfill_since is advisory for now. mbsync has no
 * usable server-side date-range fetch, so the initial sync is fetch-all-then-
 * archive; backfill_since is preserved for a future client-side date filter and
 * is deliberately NOT silently consulted here (see the sync config task).
 *
 * Per-account concurrency is 1 (WithoutOverlapping keyed on the account id):
 * IMAP/mbsync for one account is strictly serial. An overlapping run is dropped
 * (dontRelease) rather than queued — the next scheduled sync picks it up.
 */
class SyncMailAccount implements ShouldQueue
{
    use Queueable;

    /** mbsync itself is bounded to 300s; leave room for the fan-out on top. */
    public int $timeout = 600;

    /**
     * Fixed, non-secret last_error for the identity-key pre-flight gate. Not
     * routed through Redactor::redact(): it is a literal author-written
     * sentence with no interpolated/user-controlled content, so there is
     * nothing it could leak.
     */
    private const NO_IDENTITY_KEY_ERROR = 'Vault not yet unlocked — unlock your vault once so the server can seal archived mail to your keys.';

    public function __construct(public int $accountId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('mail-sync-account-'.$this->accountId))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(MbsyncRunner $runner): void
    {
        $account = MailAccount::find($this->accountId);
        if ($account === null || ! $account->enabled) {
            return;
        }

        MailLogger::record($account, 'info', 'sync_started');

        // Pre-flight BEFORE any fetch: see the class docblock's step 0. Do not
        // pull a single byte of mail this run cannot immediately seal.
        [$x25519Pub, $mlkemEk] = MaildirIngestor::ownerIdentity($account);
        if ($x25519Pub === null || $mlkemEk === null) {
            $account->forceFill([
                'status' => 'error',
                'last_error' => self::NO_IDENTITY_KEY_ERROR,
            ])->save();
            MailLogger::record($account, 'warn', 'no_identity_key', null, self::NO_IDENTITY_KEY_ERROR);

            return;
        }

        $account->forceFill(['status' => 'syncing'])->save();

        $result = $runner->run($account);
        if (! $result->ok) {
            // Failed/HostRejected: the runner already recorded this (recording
            // again is idempotent). Unavailable: the runner left the account
            // untouched, so we record the error here. Either way: no ingest.
            $redacted = Redactor::redact((string) ($result->message ?? 'IMAP sync failed.'));
            $account->forceFill([
                'status' => 'error',
                'last_error' => $redacted,
            ])->save();
            MailLogger::record($account, 'error', 'mbsync_failed', null, $redacted);

            return;
        }

        MailLogger::record($account, 'info', 'mbsync_ok', null, 'Fetch complete; paging folders for ingest.');

        // Fetch succeeded (the runner cleared status to idle); hold 'syncing'
        // through the ingest phase so the account only rests once archiving is
        // done, not the moment the bytes hit local disk.
        $account->forceFill(['status' => 'syncing', 'last_error' => null])->save();

        $chunks = $this->buildChunks($account);
        if ($chunks === []) {
            MailLogger::record($account, 'info', 'nothing_to_ingest', null, 'No new messages fetched this run.');
            self::markIdle($this->accountId);

            return;
        }

        $accountId = $this->accountId;
        // The finally closure is serialized into job_batches, so it must NOT
        // capture $this (the job): it captures only the account id and calls a
        // static settler.
        $batch = Bus::batch($chunks)
            ->name('mail-ingest-'.$accountId)
            // One poison chunk must never cancel the batch or block the rest —
            // the ingestor leaves failed files in place for the next sync.
            ->allowFailures()
            ->finally(function (Batch $batch) use ($accountId): void {
                SyncMailAccount::markIdle($accountId);
            })
            ->dispatch();

        // Record the batch id so a user can cancel this in-flight ingest
        // (MailAccountController::cancelSync → Bus::findBatch(...)->cancel();
        // every IngestMailChunk checks $this->batch()?->cancelled()).
        $account->forceFill(['sync_batch_id' => $batch->id])->save();
    }

    /**
     * Page the account's Maildir tree into bounded IngestMailChunk jobs. A
     * "folder" is any directory under the Maildir root that holds a `cur/`
     * subdir (Maildir convention); its ledger name is the path relative to the
     * root (e.g. "INBOX", "Archive/2024"). Files are streamed per folder and
     * sliced so no single job carries more than the configured chunk size.
     *
     * @return list<IngestMailChunk>
     */
    private function buildChunks(MailAccount $account): array
    {
        $root = MbsyncRunner::maildirPathFor($account);
        if (! is_dir($root)) {
            return [];
        }

        $configured = config('mail_archive.ingest_chunk_size', 100);
        $size = max(1, min(1000, is_numeric($configured) ? (int) $configured : 100));

        // Backlog throttle: cap how many messages one sync run ingests. Sealing
        // spawns a Node process PER message, so paging a whole 8000-message
        // mailbox into one batch can saturate the host (a real incident). With a
        // cap, a large first-time mailbox drains gently over several scheduled
        // runs — the un-paged files stay durably in the Maildir and are picked up
        // next run (dedup makes it idempotent). 0/unset = no cap.
        $maxCfg = config('mail_archive.ingest_max_per_run', 800);
        $maxPerRun = is_numeric($maxCfg) ? (int) $maxCfg : 800;

        $jobs = [];
        $paged = 0;
        foreach ($this->folders($root) as $folder => $dir) {
            $files = $this->messageFiles($dir);
            // Per-folder visibility: shows the owner EXACTLY which folders the
            // sync fetched and how many new messages each has this run (the
            // "only INBOX?" diagnostic). A folder with 0 new files still logs.
            MailLogger::record($account, 'info', 'folder_fetched', $folder, count($files).' new message(s) to ingest.');
            if ($maxPerRun > 0 && $paged >= $maxPerRun) {
                // Cap reached — leave the rest of this (and further) folders in
                // the Maildir for the next run.
                continue;
            }
            if ($maxPerRun > 0 && $paged + count($files) > $maxPerRun) {
                $files = array_slice($files, 0, $maxPerRun - $paged);
            }
            $paged += count($files);
            foreach (array_chunk($files, $size) as $slice) {
                $jobs[] = new IngestMailChunk($account->id, $folder, $slice);
            }
        }
        if ($maxPerRun > 0 && $paged >= $maxPerRun) {
            MailLogger::record($account, 'info', 'ingest_capped', null, "Ingest capped at {$maxPerRun} this run; the rest continues next sync.");
        }

        return $jobs;
    }

    /**
     * Every Maildir folder under $root, as [relative-folder-name => abs-path].
     * A directory is a folder iff it contains a `cur/` subdir. Walks the whole
     * tree so nested folders (SubFolders Verbatim) are all discovered.
     *
     * @return array<string, string>
     */
    private function folders(string $root): array
    {
        $root = rtrim($root, '/');
        $found = [];
        $stack = [$root];

        while ($stack !== []) {
            $dir = array_pop($stack);
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (! is_dir($path)) {
                    continue;
                }
                // A Maildir folder is marked by its cur/ subdir; cur/new/tmp
                // themselves are the folder's internals, never folders.
                if (in_array($entry, ['cur', 'new', 'tmp'], true)) {
                    continue;
                }
                if (is_dir($path.'/cur')) {
                    $found[ltrim(substr($path, strlen($root)), '/')] = $path;
                }
                $stack[] = $path; // descend for nested folders
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Absolute paths of every message file in a folder's cur/ and new/ dirs
     * (Maildir delivery states), skipping dotfiles (incl. our own
     * `.quarantine/`) and non-files.
     *
     * @return list<string>
     */
    private function messageFiles(string $folderDir): array
    {
        $files = [];
        foreach (['cur', 'new'] as $sub) {
            $dir = $folderDir.'/'.$sub;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $entry) {
                if (str_starts_with($entry, '.')) {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (is_file($path)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Return the account to a resting state after ingest. Only flips a still-
     * 'syncing' account so a concurrently-set error state is never clobbered.
     */
    private static function markIdle(int $accountId): void
    {
        try {
            $account = MailAccount::find($accountId);
            if ($account !== null && $account->status === 'syncing') {
                $account->forceFill(['status' => 'idle', 'last_synced_at' => now(), 'sync_batch_id' => null])->save();
                MailLogger::record($account, 'info', 'sync_done', null, 'Sync finished.');
            }
        } catch (Throwable) {
            // Best-effort resting state; the next sync will settle the status.
        }
    }
}
