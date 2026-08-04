<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Support\BlobStore;
use App\Support\Mail\MailSealer;
use App\Support\Mail\SpamHeaders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The loss-safety CORE of the mail archive: turns a fetched-but-plaintext
 * Maildir message file into a sealed, deduplicated, durably ledgered archive
 * entry — then, and only then, shreds the plaintext file.
 *
 * The invariant that makes this safe against crashes, retries, and partial
 * failures is a strict ordering, per file:
 *
 *   read → sha256 → DEDUP → resolve owner keys → SEAL → put blob → ledger row
 *   (in a DB transaction) → COMMIT → unlink the Maildir file.
 *
 * The Maildir file is the source of truth until the very end: it is NEVER
 * unlinked before the ledger row is durably committed. Consequences:
 *   - Blob write succeeds, ledger write fails → the transaction rolls back both
 *     ledger rows; the blob bytes are an orphan the sweep reclaims; the Maildir
 *     file is still present, so the next run re-seals and re-stores cleanly.
 *   - Commit succeeds but the final unlink fails → the file survives; the next
 *     run's dedup check finds the message and unlinks it. No duplicate, no loss.
 *   - Any seal / blob-write / ledger error → propagates out of ingestFile so the
 *     file stays for a retry (never a silent partial success).
 *
 * De-duplication is by whole-message sha256 (mail is small): a second identical
 * message — same-run copy or a re-sync of an already-archived one — is unlinked
 * without re-storing and without ever deleting an existing ledger row.
 *
 * Identity-key gating: the message is sealed to the ACCOUNT OWNER's published
 * public identity keys (X25519 + ML-KEM-768). If the owner has never unlocked
 * their vault, those keys do not exist yet — the ingestor MUST NOT drop the
 * mail. It returns NotSealable and leaves the Maildir file in place so a later
 * run archives it once the keys are published. The sync PRODUCER
 * (App\Jobs\Mail\SyncMailAccount) pre-flights this same check via
 * self::ownerIdentity() before ever invoking mbsync, so a keyless owner's
 * mailbox is never fetched into the durable Maildir in the first place — the
 * per-message check here remains as a defence-in-depth backstop (e.g. a chunk
 * still holding files fetched before keys existed).
 *
 * A file that cannot be READ is quarantined (moved aside) + logged — never
 * silently dropped and never allowed to crash the whole folder loop.
 */
class MaildirIngestor
{
    public function __construct(private readonly MailSealer $sealer) {}

    /**
     * Ingest one Maildir message file. See the class docblock for the full
     * ordering contract. Throws only on seal / blob-write / ledger-write
     * failure — in which case the Maildir file is left in place for a retry.
     *
     * @throws RuntimeException|Throwable on a non-recoverable seal/store failure
     */
    public function ingestFile(MailAccount $account, string $folder, string $path): IngestResult
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            // Unreadable/vanished file: never silently dropped — move it aside so
            // it is preserved for inspection and not retried forever, and log it.
            Log::warning('mail.ingest.unreadable', [
                'account_id' => $account->id,
                'path' => $path,
            ]);
            $this->quarantine($path);

            return IngestResult::quarantined();
        }

        // Whole-message hash — mail is small enough not to need the head/tail
        // fileSig trick, and a full hash cannot collide two distinct messages.
        $rawSize = strlen($raw);
        $hash = hash('sha256', $raw);

        // DEDUP: already archived for this user → confirmed duplicate. Unlink the
        // Maildir copy (its content is safely stored) and NEVER touch the ledger.
        if ($this->alreadyArchived($account, $hash)) {
            sodium_memzero($raw);
            @unlink($path);

            return IngestResult::duplicate($hash);
        }

        // backfill_since (Option B): mbsync has no server-side date filter, so an
        // initial sync still downloads the whole mailbox — but we only ARCHIVE
        // messages that arrived on/after the account's cut-off. Older ones are
        // dropped from our local scratch Maildir; the ORIGIN mailbox is never
        // touched (pull-only), so this is not data loss. Arrival time = the
        // Maildir file mtime (mbsync `CopyArrivalDate yes` stamps it with the
        // IMAP INTERNALDATE). Fail OPEN: if the cut-off or the mtime is missing,
        // archive the message rather than risk dropping a wanted one.
        $cutoff = $account->backfill_since;
        if ($cutoff !== null) {
            $arrival = @filemtime($path);
            if ($arrival !== false && $arrival < $cutoff->copy()->startOfDay()->getTimestamp()) {
                sodium_memzero($raw);
                @unlink($path);

                return IngestResult::skippedOld($hash);
            }
        }

        // Spam filter: if the account skips spam and the origin server flagged
        // this message (X-Spam-Flag / rspamd / etc.), do NOT archive it — the
        // immutable archive never receives spam. Drop the local Maildir copy
        // (origin mailbox untouched). Checked from the RAW headers before seal.
        if ($account->skip_spam && SpamHeaders::isSpamRaw($raw)) {
            sodium_memzero($raw);
            @unlink($path);

            return IngestResult::skippedSpam($hash);
        }

        [$x25519Pub, $mlkemEk] = self::ownerIdentity($account);
        if ($x25519Pub === null || $mlkemEk === null) {
            // Cannot seal yet: the owner has not published identity keys. Leave
            // the file untouched so a later run archives it — losing it here would
            // be an irrecoverable drop. Scrub our plaintext copy regardless.
            sodium_memzero($raw);
            Log::info('mail.ingest.no_identity_key', [
                'account_id' => $account->id,
                'user_id' => $account->user_id,
            ]);

            return IngestResult::notSealable($hash);
        }

        // Seal scrubs $raw to null (by reference). From here on, any failure MUST
        // leave the Maildir file un-unlinked — so we only unlink after the commit.
        $sealed = $this->sealer->seal($raw, $x25519Pub, $mlkemEk);

        // Blob bytes first (durable, non-transactional). If this fails, no ledger
        // row is written and the file stays; if it succeeds but the ledger write
        // below fails, these bytes become an orphan the sweep reclaims.
        //
        // The message row has no separate "which blob" column (Task 8's ledger
        // contract is deliberately just id/account_id/folder/size/created_at/
        // sealed_key) — so the message's own id IS the blob's primary key: one
        // fresh UUID names both `mail/{id}` on disk and the mail_messages row
        // that describes it. A client resolves the raw ciphertext for a listed
        // message with `GET /mail/raw/{message.id}`, no extra lookup needed.
        $blobId = (string) Str::uuid();
        if (BlobStore::disk()->put('mail/'.$blobId, $sealed['blob']) === false) {
            throw new RuntimeException('MaildirIngestor: failed to write sealed mail blob to disk.');
        }

        // Read the origin \Seen state from the Maildir filename flags (cur/ files
        // carry ":2,<flags>" where S = Seen; new/ files are unseen). Stored so a
        // later push-back can restore the read state on the origin server.
        $seen = $this->maildirSeen($path);

        DB::transaction(function () use ($account, $folder, $hash, $rawSize, $sealed, $blobId, $seen): void {
            // Hour-snapped timestamps: never leak the exact arrival time (mirrors
            // the padding/created_at discipline of every other sealed module).
            $now = now()->startOfHour();

            MailBlob::query()->create([
                'blob' => $blobId,
                'user_id' => $account->user_id,
                'size' => strlen($sealed['blob']),
                'created_at' => $now,
            ]);

            $message = new MailMessage([
                'id' => $blobId,
                'account_id' => $account->id,
                'folder' => $folder,
                'seen' => $seen,
                'content_hash' => $hash,
                'size' => $rawSize,
                'sealed_key' => $sealed['sealed_key'],
                'created_at' => $now,
            ]);
            // user_id is stamped from context (AssignsOwner), not fillable from
            // request input — set it directly, as a worker does with no auth session.
            $message->user_id = $account->user_id;
            $message->save();
        });

        // Ledger row is durably committed → the plaintext is now redundant. Shred
        // it. The raw plaintext buffer was already scrubbed by seal(); the copy
        // whose origin (this file) we now remove.
        @unlink($path);

        // mbsync stamps each Maildir filename with the origin IMAP UID as
        // `,U=<uid>` — extract it so "delete after import" can remove exactly
        // this message from the origin server. Absent (non-mbsync layout) → null,
        // and the delete step skips it (never guesses a UID).
        $uid = preg_match('/,U=(\d+)/', basename($path), $m) ? $m[1] : null;

        return IngestResult::stored($hash, $uid);
    }

    /**
     * Ingest every message file in a Maildir folder's `cur/` and `new/`
     * subdirectories. One bad file (unreadable, or a seal/store failure) is
     * logged and counted but never crashes the loop — the remaining valid
     * messages are still archived, and nothing that failed is lost (its Maildir
     * file stays for a retry, or was quarantined).
     *
     * @return array{stored:int, duplicate:int, not_sealable:int, quarantined:int, failed:int}
     */
    public function ingestFolder(MailAccount $account, string $folder, string $maildirPath): array
    {
        $summary = ['stored' => 0, 'duplicate' => 0, 'not_sealable' => 0, 'quarantined' => 0, 'skipped_old' => 0, 'skipped_spam' => 0, 'failed' => 0];

        foreach (['cur', 'new'] as $sub) {
            $dir = rtrim($maildirPath, '/').'/'.$sub;
            if (! is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) ?: [] as $entry) {
                // Skip `.`, `..`, and dotfiles (incl. our own `.quarantine/`).
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
                    // Seal / blob-write / ledger error. The file was NOT unlinked
                    // (ingestFile only unlinks after commit), so it survives for a
                    // retry — nothing is lost. Do not let it abort the folder.
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

    /**
     * The account owner's published public identity keys (X25519 pub + ML-KEM
     * encapsulation key, both base64). Either being absent means the owner has
     * not provisioned their vault yet — return [null, null] so ingestFile leaves
     * the mail for a later run.
     *
     * Public + static (no instance state involved) so the sync producer can
     * run the identical check BEFORE fetching anything — see the class
     * docblock's "Identity-key gating" note.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function ownerIdentity(MailAccount $account): array
    {
        $user = $account->user()->first(['id', 'x25519_public_key', 'mlkem_public_key']);
        if ($user === null) {
            return [null, null];
        }

        $pub = $user->x25519_public_key;
        $ek = $user->mlkem_public_key;

        return [
            is_string($pub) && $pub !== '' ? $pub : null,
            is_string($ek) && $ek !== '' ? $ek : null,
        ];
    }

    /**
     * Move an unreadable file aside into a sibling `.quarantine/` directory so it
     * is preserved (never silently dropped) and not retried forever. Best-effort:
     * a missing/vanished file simply has nothing to move.
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
