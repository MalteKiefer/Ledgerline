<?php

declare(strict_types=1);

return [
    /*
     * How often the scheduler dispatches a sync for every enabled mail account
     * (minutes, within the hour: clamped to 1..59 for the cron expression).
     * Each dispatch is a queued producer job (SyncMailAccount) guarded by a
     * per-account no-overlap lock, so a slow sync never stacks up.
     */
    'sync_interval_minutes' => (int) env('MAIL_SYNC_INTERVAL_MINUTES', 30),

    /*
     * How many Maildir message files each ingest worker (IngestMailChunk)
     * processes. The producer pages the fetched Maildir into chunks of this
     * size instead of enqueuing one job per message, so a 100k-message mailbox
     * becomes ~1k retryable chunk jobs, not 100k. Clamped to 1..1000.
     */
    'ingest_chunk_size' => (int) env('MAIL_INGEST_CHUNK_SIZE', 100),

    /*
     * Grace before an unreferenced sealed mail blob on disk (mail/{blob}) is
     * reclaimed by mail:sweep-orphans — long enough that a blob whose MailBlob
     * ledger row commits in the same transaction as its MailMessage a moment
     * later is never mistaken for an orphan. Mirrors the sibling blob modules
     * (CONTACTS_/EXPLORE_/FILES_BLOB_ORPHAN_GRACE_HOURS).
     */
    'blob_orphan_grace_hours' => (int) env('MAIL_ARCHIVE_BLOB_ORPHAN_GRACE_HOURS', 24),
];
