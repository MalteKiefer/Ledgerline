<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * The outcome of ingesting a single Maildir message file. Immutable value
 * object returned by MaildirIngestor::ingestFile().
 *
 * The four statuses are the complete, loss-safe decision space for one file:
 *   - Stored:       sealed, durably ledgered, and the Maildir file shredded.
 *   - Duplicate:    already archived (content_hash match) → file unlinked,
 *                   nothing re-stored.
 *   - NotSealable:  the account owner has not published identity keys yet, so
 *                   the message CANNOT be sealed — the file is LEFT untouched
 *                   so a later run (once keys exist) archives it. Nothing lost.
 *   - Quarantined:  the file could not be read → moved aside + logged, never
 *                   silently dropped.
 *
 * Any OTHER failure (seal/blob-write/ledger-write error) is deliberately NOT a
 * status: it throws out of ingestFile so the Maildir file stays un-unlinked and
 * the caller retries. See MaildirIngestor for the full contract.
 */
final readonly class IngestResult
{
    /** True only for a freshly stored message; convenience over `status`. */
    public bool $stored;

    public function __construct(
        public IngestStatus $status,
        public string $hash,
    ) {
        $this->stored = $status === IngestStatus::Stored;
    }

    public static function stored(string $hash): self
    {
        return new self(IngestStatus::Stored, $hash);
    }

    public static function duplicate(string $hash): self
    {
        return new self(IngestStatus::Duplicate, $hash);
    }

    public static function notSealable(string $hash): self
    {
        return new self(IngestStatus::NotSealable, $hash);
    }

    public static function quarantined(): self
    {
        return new self(IngestStatus::Quarantined, '');
    }
}
