<?php

declare(strict_types=1);

return [
    /*
     * How often the scheduler dispatches a sync for every enabled mail account
     * (minutes). Each dispatch is a queued producer job (SyncMailAccount)
     * guarded by a per-account no-overlap lock, so a slow sync never stacks up.
     * A per-account override (mail_accounts.sync_interval_minutes) wins.
     */
    'sync_interval_minutes' => (int) env('MAIL_SYNC_INTERVAL_MINUTES', 30),

    /*
     * How many Maildir message files each ingest worker (IngestMailChunk)
     * processes. The producer pages the fetched Maildir into chunks of this size
     * instead of enqueuing one job per message, so a 100k-message mailbox
     * becomes ~1k retryable chunk jobs, not 100k. Clamped to 1..1000.
     */
    'ingest_chunk_size' => (int) env('MAIL_INGEST_CHUNK_SIZE', 100),

    /*
     * Backlog throttle: max messages ingested per sync run. MIME parsing +
     * HTML sanitising is CPU per message, so a whole large mailbox in one batch
     * can saturate the host; a big first-time mailbox drains over several runs.
     * 0 = no cap.
     */
    'ingest_max_per_run' => (int) env('MAIL_INGEST_MAX_PER_RUN', 800),

    /*
     * Grace before an unreferenced raw mail blob on disk (mail/{blob}) is
     * reclaimed by mail:sweep-orphans — long enough that a blob whose MailBlob
     * ledger row commits in the same transaction as its MailMessage a moment
     * later is never mistaken for an orphan.
     */
    'blob_orphan_grace_hours' => (int) env('MAIL_ARCHIVE_BLOB_ORPHAN_GRACE_HOURS', 24),

    // Diagnostic per-account sync/ingest log retention (days). Metadata only.
    'log_retention_days' => (int) env('MAIL_ARCHIVE_LOG_RETENTION_DAYS', 30),
];
