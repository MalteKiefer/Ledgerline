<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Models\MailRule;
use App\Services\Files\FileTextIndex;
use App\Support\BlobStore;
use App\Support\Mail\MailHtmlSanitizer;
use App\Support\Mail\MimeParser;
use App\Support\Mail\RuleEvaluator;
use App\Support\Mail\SpamHeaders;
use App\Support\Mail\ThreadId;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The loss-safety CORE of the mail archive: turns a fetched-but-plaintext
 * Maildir message file into a parsed, denormalised, deduplicated, durably
 * ledgered archive entry — then, and only then, shreds the Maildir file.
 *
 * Non-ZK (plaintext-relational): there is no sealing and no identity-key
 * gating. The server parses the RFC822 message itself (headers, bodies,
 * attachment count) into denormalised columns + a full-text search string,
 * sanitises the HTML body, and stores the raw .eml bytes plaintext on the files
 * disk. The raw .eml blob remains the immutable source of truth.
 *
 * The invariant that makes this safe against crashes, retries and partial
 * failures is a strict ordering, per file:
 *
 *   read → sha256 → DEDUP → parse → sanitise → put blob → ledger rows (DB txn)
 *   → COMMIT → unlink the Maildir file.
 *
 * The Maildir file is the source of truth until the very end — it is NEVER
 * unlinked before the ledger row is durably committed. Consequences:
 *   - Blob write ok, ledger write fails → the txn rolls back the rows; the blob
 *     bytes are an orphan the sweep reclaims; the Maildir file is still present,
 *     so the next run re-stores cleanly.
 *   - Commit ok but the final unlink fails → the file survives; the next run's
 *     dedup finds the message and unlinks it. No duplicate, no loss.
 *   - Any parse / blob-write / ledger error → propagates out of ingestFile so
 *     the file stays for a retry (never a silent partial success).
 *
 * De-duplication is by whole-message sha256 (mail is small): a second identical
 * message is unlinked without re-storing and without deleting an existing row.
 *
 * A file that cannot be READ is quarantined (moved aside) + logged — never
 * silently dropped and never allowed to crash the whole folder loop.
 */
class MaildirIngestor
{
    /** Cap the stored full-text search string (the body can be large). */
    private const SEARCH_TEXT_MAX = 200_000;

    /** Max attachments per message we OCR/extract text from into the index. */
    private const OCR_ATTACHMENTS_MAX = 3;

    /** Per-user enabled-rule cache for one ingestFolder run (avoids N queries). */
    /** @var array<int, Collection<int, MailRule>> */
    private array $rulesCache = [];

    public function __construct(
        private readonly MimeParser $parser = new MimeParser,
        private readonly MailHtmlSanitizer $sanitizer = new MailHtmlSanitizer,
        private readonly MailDecryptor $decryptor = new MailDecryptor,
        private readonly RuleEvaluator $ruleEvaluator = new RuleEvaluator,
        private readonly FileTextIndex $textIndex = new FileTextIndex,
    ) {}

    /**
     * Ingest one Maildir message file. See the class docblock for the full
     * ordering contract. Throws only on parse / blob-write / ledger-write
     * failure — in which case the Maildir file is left in place for a retry.
     *
     * @throws RuntimeException|Throwable on a non-recoverable parse/store failure
     */
    public function ingestFile(MailAccount $account, string $folder, string $path): IngestResult
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            Log::warning('mail.ingest.unreadable', ['account_id' => $account->id, 'path' => $path]);
            $this->quarantine($path);

            return IngestResult::quarantined();
        }

        // Whole-message hash — mail is small enough not to need the head/tail
        // trick, and a full hash cannot collide two distinct messages.
        $rawSize = strlen($raw);
        $hash = hash('sha256', $raw);

        // DEDUP: already archived for this user → confirmed duplicate. Unlink the
        // Maildir copy (its content is safely stored) and NEVER touch the ledger.
        if ($this->alreadyArchived($account, $hash)) {
            @unlink($path);

            return IngestResult::duplicate($hash);
        }

        // backfill_since: mbsync has no server-side date filter, so an initial
        // sync still downloads the whole mailbox — but we only ARCHIVE messages
        // that arrived on/after the cut-off. Older ones are dropped from local
        // scratch; the ORIGIN mailbox is never touched (pull-only), so this is
        // not data loss. Arrival = the Maildir file mtime (mbsync
        // `CopyArrivalDate yes` = IMAP INTERNALDATE). Fail OPEN.
        $cutoff = $account->backfill_since;
        if ($cutoff !== null) {
            $arrival = @filemtime($path);
            if ($arrival !== false && $arrival < $cutoff->copy()->startOfDay()->getTimestamp()) {
                @unlink($path);

                return IngestResult::skippedOld($hash);
            }
        }

        // Spam filter: if the account skips spam and the origin server flagged
        // this message, do NOT archive it (the immutable archive never receives
        // spam). Drop the local Maildir copy; origin mailbox untouched.
        $spam = SpamHeaders::isSpamRaw($raw);
        if ($account->skip_spam && $spam) {
            @unlink($path);

            return IngestResult::skippedSpam($hash);
        }

        // Server-side MIME parse + HTML sanitise (replaces the ZK client parse).
        $parsed = $this->parser->parse($raw);

        // PGP / S-MIME: if the message is encrypted and the owner has a matching
        // key, decrypt server-side (transient — the raw .eml stays as received)
        // and take the body + attachments from the DECRYPTED inner message. The
        // envelope (from/to/subject/date) always stays from the outer headers.
        $decrypt = $this->decryptor->attempt($raw, (int) $account->user_id);
        $textBody = $parsed->textBody;
        $htmlBody = $parsed->htmlBody;
        $attachments = $parsed->attachments;
        if ($decrypt->status === 'ok' && $decrypt->plaintext !== null) {
            if ($decrypt->isMime) {
                $inner = $this->parser->parse($decrypt->plaintext);
                $textBody = $inner->textBody;
                $htmlBody = $inner->htmlBody;
                $attachments = $inner->attachments;
            } else {
                $textBody = $decrypt->plaintext;
                $htmlBody = null;
                $attachments = [];
            }
        }
        $attachmentCount = $decrypt->status === 'ok' ? count($attachments) : $parsed->attachmentCount;
        $htmlSanitized = $this->sanitizer->sanitize($htmlBody);

        // Ingest rules (sieve-lite): match the parsed envelope; a "skip" action
        // drops the message (like spam), the rest adjust how it is stored.
        $rule = $this->ruleEvaluator->evaluate(
            $this->rulesFor((int) $account->user_id),
            [
                'from' => trim(($parsed->fromName ?? '').' '.($parsed->fromEmail ?? '')),
                'to' => $this->addressText($parsed),
                'subject' => (string) $parsed->subject,
                'folder' => $folder,
                'has_attachment' => $attachmentCount > 0,
            ],
        );
        if ($rule['skip']) {
            @unlink($path);

            return IngestResult::skippedRule($hash);
        }

        $threadId = ThreadId::for($parsed->references, $parsed->inReplyTo, $parsed->messageId, $parsed->subject);
        $searchText = $this->buildSearchText($parsed, $textBody, $attachments, $this->attachmentText($attachments));

        // The message's own id doubles as the raw blob's primary key: one fresh
        // UUID names both `mail/{id}` on disk and the mail_messages row. A client
        // fetches the raw .eml with `GET /mail/raw/{id}`, no extra lookup needed.
        $blobId = (string) Str::uuid();
        if (BlobStore::disk()->put('mail/'.$blobId, $raw) === false) {
            throw new RuntimeException('MaildirIngestor: failed to write mail blob to disk.');
        }

        // Decoded attachment parts (real + inline/cid). Write each part's
        // plaintext bytes to mail/att/{uuid} BEFORE the txn — same loss-safety
        // posture as the raw blob: a txn failure leaves them as orphans the
        // sweep reclaims, and the Maildir file survives for a clean retry.
        //
        // @var list<array{blob:string, attachment:\App\Services\Mail\ParsedAttachment}> $attachmentBlobs
        $attachmentBlobs = [];
        foreach ($attachments as $attachment) {
            $attBlobId = (string) Str::uuid();
            if (BlobStore::disk()->put('mail/att/'.$attBlobId, $attachment->bytes) === false) {
                throw new RuntimeException('MaildirIngestor: failed to write mail attachment blob to disk.');
            }
            $attachmentBlobs[] = ['blob' => $attBlobId, 'attachment' => $attachment];
        }

        // Origin \Seen state from the Maildir filename flags (cur/ carries
        // ":2,<flags>" where S = Seen; new/ files are unseen). A mark_read rule
        // forces seen; a trash rule stores the message soft-hidden.
        $seen = $this->maildirSeen($path) || $rule['mark_read'];

        DB::transaction(function () use ($account, $folder, $hash, $rawSize, $blobId, $seen, $spam, $parsed, $textBody, $htmlSanitized, $searchText, $attachmentBlobs, $attachmentCount, $decrypt, $threadId, $rule): void {
            // Hour-snapped archived-at (mirrors every other module's created_at).
            $now = now()->startOfHour();

            (new MailBlob)->forceFill([
                'blob' => $blobId,
                'user_id' => $account->user_id,
                'kind' => 'message',
                'size' => $rawSize,
                'created_at' => $now,
            ])->save();

            $message = new MailMessage;
            $message->forceFill([
                'id' => $blobId,
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'folder' => $folder,
                'content_hash' => $hash,
                'size' => $rawSize,
                'message_id' => $this->cap($parsed->messageId, 255),
                'in_reply_to' => $this->cap($parsed->inReplyTo, 255),
                'references' => $parsed->references,
                'thread_id' => $threadId,
                'subject' => $parsed->subject,
                'from_name' => $this->cap($parsed->fromName, 255),
                'from_email' => $this->cap($parsed->fromEmail, 255),
                'to_json' => $parsed->to,
                'cc_json' => $parsed->cc,
                'reply_to' => $this->cap($parsed->replyTo, 255),
                'date' => $parsed->date,
                'has_attachment' => $attachmentCount > 0,
                'attachment_count' => $attachmentCount,
                'text_body' => $textBody,
                'html_sanitized' => $htmlSanitized,
                'spam' => $spam,
                'spf' => $parsed->spf,
                'dkim' => $parsed->dkim,
                'dmarc' => $parsed->dmarc,
                'encrypted_type' => $decrypt->type,
                'decrypt_status' => $decrypt->status,
                'seen' => $seen,
                'seen_at' => null,
                'trashed_at' => $rule['trash'] ? $now : null,
                'created_at' => $now,
                'search_text' => $searchText,
                'indexed_at' => $now,
            ]);
            $message->save();

            foreach ($attachmentBlobs as $entry) {
                $attachment = $entry['attachment'];

                (new MailBlob)->forceFill([
                    'blob' => $entry['blob'],
                    'user_id' => $account->user_id,
                    'kind' => 'attachment',
                    'size' => $attachment->size(),
                    'created_at' => $now,
                ])->save();

                (new MailAttachment)->forceFill([
                    'id' => (string) Str::uuid(),
                    'message_id' => $blobId,
                    'user_id' => $account->user_id,
                    'blob' => $entry['blob'],
                    'filename' => $this->cap($attachment->filename, 500),
                    'content_type' => $this->cap($attachment->contentType, 255),
                    'content_id' => $this->cap($attachment->contentId, 512),
                    'inline' => $attachment->inline,
                    'size' => $attachment->size(),
                    'created_at' => $now,
                ])->save();
            }

            // Ingest-rule labels: only labels the user actually owns.
            if ($rule['label_ids'] !== []) {
                $ownLabelIds = MailLabel::query()
                    ->ownedBy($account->user_id)
                    ->whereIn('id', $rule['label_ids'])
                    ->pluck('id')
                    ->all();
                if ($ownLabelIds !== []) {
                    $message->labels()->syncWithoutDetaching($ownLabelIds);
                }
            }
        });

        // Ledger row committed → the Maildir plaintext is redundant. Shred it.
        @unlink($path);

        // mbsync stamps each Maildir filename with the origin IMAP UID as
        // `,U=<uid>` — extract it so a future "delete after import" (Phase 4) can
        // remove exactly this message. Absent → null (delete step skips it).
        $uid = preg_match('/,U=(\d+)/', basename($path), $m) === 1 ? $m[1] : null;

        return IngestResult::stored($hash, $uid);
    }

    /**
     * Ingest every message file in a Maildir folder's `cur/` and `new/`
     * subdirectories. One bad file is logged + counted but never crashes the
     * loop — the remaining valid messages are still archived, and nothing that
     * failed is lost (its Maildir file stays for a retry, or was quarantined).
     *
     * @return array{stored:int, duplicate:int, quarantined:int, skipped_old:int, skipped_spam:int, skipped_rule:int, failed:int}
     */
    public function ingestFolder(MailAccount $account, string $folder, string $maildirPath): array
    {
        $summary = ['stored' => 0, 'duplicate' => 0, 'quarantined' => 0, 'skipped_old' => 0, 'skipped_spam' => 0, 'skipped_rule' => 0, 'failed' => 0];

        foreach (['cur', 'new'] as $sub) {
            $dir = rtrim($maildirPath, '/').'/'.$sub;
            if (! is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) ?: [] as $entry) {
                if (str_starts_with($entry, '.')) {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (! is_file($path)) {
                    continue;
                }

                try {
                    $result = $this->ingestFile($account, $folder, $path);
                    $summary[$result->status->value]++;
                } catch (Throwable $e) {
                    // Parse / blob-write / ledger error. The file was NOT unlinked
                    // (ingestFile only unlinks after commit), so it survives for a
                    // retry — nothing is lost. Do not abort the folder.
                    $summary['failed']++;
                    Log::warning('mail.ingest.failed', [
                        'account_id' => $account->id,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $summary;
    }

    /**
     * The user's enabled ingest rules (priority order), cached per user for the
     * duration of one ingestFolder run so a big mailbox doesn't re-query per file.
     *
     * @return Collection<int, MailRule>
     */
    private function rulesFor(int $userId): Collection
    {
        return $this->rulesCache[$userId] ??= MailRule::query()
            ->ownedBy($userId)
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /** Combined recipient text (to + cc, names + emails) for rule matching. */
    private function addressText(ParsedMessage $parsed): string
    {
        $parts = [];
        foreach ([...$parsed->to, ...$parsed->cc] as $addr) {
            $parts[] = $addr['name'];
            $parts[] = $addr['email'];
        }

        return trim(implode(' ', array_filter($parts, static fn (?string $p): bool => $p !== null && $p !== '')));
    }

    /**
     * Best-effort OCR/text extraction of the first few PDF/image attachments,
     * folded into the search index. Bounded (count + FileTextIndex's own size /
     * timeout caps); any failure is silently skipped.
     *
     * @param  list<ParsedAttachment>  $attachments
     */
    private function attachmentText(array $attachments): ?string
    {
        $out = [];
        $done = 0;
        foreach ($attachments as $att) {
            if ($done >= self::OCR_ATTACHMENTS_MAX) {
                break;
            }
            $mime = strtolower((string) $att->contentType);
            if ($mime !== 'application/pdf' && ! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'text/')) {
                continue;
            }
            $done++;
            $text = $this->textIndex->extractBytes($att->bytes, $mime);
            if ($text !== null && $text !== '') {
                $out[] = $text;
            }
        }

        return $out === [] ? null : implode(' ', $out);
    }

    /**
     * The server-side full-text search string: envelope (subject + participants
     * from the outer headers) + the resolved plaintext body + attachment
     * filenames. For decrypted mail the body/attachments come from the inner
     * message, so they are passed in rather than read off $parsed.
     *
     * @param  list<ParsedAttachment>  $attachments
     */
    private function buildSearchText(ParsedMessage $parsed, ?string $textBody, array $attachments, ?string $ocrText = null): ?string
    {
        $parts = [$parsed->subject, $parsed->fromName, $parsed->fromEmail];
        foreach ([...$parsed->to, ...$parsed->cc] as $addr) {
            $parts[] = $addr['name'];
            $parts[] = $addr['email'];
        }
        $parts[] = $textBody;
        foreach ($attachments as $attachment) {
            $parts[] = $attachment->filename;
        }
        $parts[] = $ocrText;

        $text = trim(implode(' ', array_filter(
            $parts,
            static fn (?string $p): bool => $p !== null && $p !== '',
        )));

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, self::SEARCH_TEXT_MAX);
    }

    /**
     * Whether a Maildir file was flagged \Seen on the origin. Maildir encodes
     * per-message flags in the filename after ":2," (info section); 'S' = Seen.
     * Files still in new/ (no info section) are unseen.
     */
    private function maildirSeen(string $path): bool
    {
        $base = basename($path);
        $pos = strpos($base, ':2,');

        return $pos !== false && str_contains(substr($base, $pos + 3), 'S');
    }

    private function alreadyArchived(MailAccount $account, string $hash): bool
    {
        return MailMessage::query()
            ->where('user_id', $account->user_id)
            ->where('content_hash', $hash)
            ->exists();
    }

    private function cap(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Move an unreadable file aside into a sibling `.quarantine/` directory so it
     * is preserved (never silently dropped) and not retried forever.
     */
    private function quarantine(string $path): void
    {
        $dir = dirname($path).'/.quarantine';
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @rename($path, $dir.'/'.basename($path).'.'.bin2hex(random_bytes(4)));
    }
}
