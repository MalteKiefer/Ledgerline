<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * The outcome of ingesting a single Maildir message file. Immutable value
 * object returned by MaildirIngestor::ingestFile().
 *
 * The statuses are the complete, loss-safe decision space for one file:
 *   - Stored:       parsed, denormalised, durably ledgered, and the Maildir
 *                   file shredded.
 *   - Duplicate:    already archived (content_hash match) → file unlinked,
 *                   nothing re-stored.
 *   - SkippedOld:   arrived before the account's backfill_since cut-off → the
 *                   local Maildir copy is unlinked (origin untouched, no loss).
 *   - SkippedSpam:  origin-flagged spam + skip_spam on → not archived, local
 *                   copy unlinked (origin untouched).
 *   - Quarantined:  the file could not be read → moved aside + logged, never
 *                   silently dropped.
 *
 * Any OTHER failure (parse / blob-write / ledger-write error) is deliberately
 * NOT a status: it throws out of ingestFile so the Maildir file stays
 * un-unlinked and the caller retries. See MaildirIngestor for the full contract.
 */
final readonly class IngestResult
{
    /** True only for a freshly stored message; convenience over `status`. */
    public bool $stored;

    public function __construct(
        public IngestStatus $status,
        public string $hash,
        /**
         * The origin IMAP UID of a freshly stored message (parsed from the
         * mbsync Maildir filename `,U=<uid>`), or null when unknown / not
         * stored. Used only to delete the message from the origin server when
         * the account has "delete after import" enabled (Phase 4).
         */
        public ?string $uid = null,
    ) {
        $this->stored = $status === IngestStatus::Stored;
    }

    public static function stored(string $hash, ?string $uid = null): self
    {
        return new self(IngestStatus::Stored, $hash, $uid);
    }

    public static function duplicate(string $hash): self
    {
        return new self(IngestStatus::Duplicate, $hash);
    }

    public static function quarantined(): self
    {
        return new self(IngestStatus::Quarantined, '');
    }

    public static function skippedOld(string $hash): self
    {
        return new self(IngestStatus::SkippedOld, $hash);
    }

    public static function skippedSpam(string $hash): self
    {
        return new self(IngestStatus::SkippedSpam, $hash);
    }

    public static function skippedRule(string $hash): self
    {
        return new self(IngestStatus::SkippedRule, $hash);
    }
}
