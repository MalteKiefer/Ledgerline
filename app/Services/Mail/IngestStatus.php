<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Terminal state of a single Maildir file ingest. The backing values double as
 * the per-status counter keys in MaildirIngestor::ingestFolder()'s summary.
 */
enum IngestStatus: string
{
    case Stored = 'stored';
    case Duplicate = 'duplicate';
    case NotSealable = 'not_sealable';
    case Quarantined = 'quarantined';
    // Message arrived before the account's backfill_since cut-off: not archived.
    // The local Maildir copy is unlinked (origin mailbox is untouched, so no
    // data loss); mbsync still downloaded it (no server-side date filter).
    case SkippedOld = 'skipped_old';
}
