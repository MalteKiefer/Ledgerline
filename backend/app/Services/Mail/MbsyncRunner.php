<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Support\BinaryProcess;
use App\Support\DiskTempFile;
use App\Support\OutboundUrl;
use App\Support\Redactor;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Runs a pull-only mbsync mirror of one account's IMAP mailbox into a local
 * Maildir scratch tree (see MbsyncConfig for the read-only-origin config this
 * shells). MaildirIngestor then parses + durably archives each fetched message
 * and shreds the plaintext Maildir copy — this class only owns the fetch step,
 * never touches the archive tables, and never mutates anything on the origin.
 *
 * Ordering, in `run()`:
 *   1. Egress-guard the configured host BEFORE anything else. A host that
 *      resolves to a link-local/metadata address is refused outright — no
 *      config rendered, no directory created, no process spawned.
 *   2. Ensure the durable scratch directories exist (state + Maildir).
 *   3. Render the config to a transient temp file (RAII DiskTempFile).
 *   4. If `mbsync` isn't installed (dev/CI box), return Unavailable WITHOUT
 *      touching the account's status/last_error.
 *   5. Shell `mbsync -c <tempconfig> -a` via BinaryProcess (array-argv). On
 *      failure, mark the account errored with a redacted tail of mbsync's own
 *      output (never the password — it is fed via PassCmd, never echoed).
 *
 * Not `final`: SyncMailAccount resolves this from the container and the test
 * suite binds a subclass fake to drive the fetch step without the network.
 */
class MbsyncRunner
{
    /**
     * First-time mirrors can contain many years of mail. Keep the fetch bounded
     * but give a resumable, read-only initial mirror enough time to finish;
     * mbsync's durable state means later runs only transfer deltas.
     */
    private const RUN_TIMEOUT = 1800;

    public function __construct(
        private readonly MbsyncConfig $config = new MbsyncConfig,
    ) {}

    public function run(MailAccount $account): MbsyncResult
    {
        $host = (string) $account->host;
        if (! OutboundUrl::hostAllowed($host)) {
            $message = 'Refusing to sync: the configured mail server host is not an allowed outbound destination.';
            $this->recordError($account, $message);

            return MbsyncResult::hostRejected($message);
        }

        $stateDir = $this->stateDir($account);
        $maildirDir = $this->maildirDir($account);
        File::ensureDirectoryExists($stateDir, 0700);
        File::ensureDirectoryExists($maildirDir, 0700);

        // render() FAILS CLOSED on a malformed account value (unsupported
        // encryption, or a control character that could inject config lines).
        $temp = DiskTempFile::create('mbsync-')->withExtension('conf');
        try {
            $rendered = $this->config->render($account, $stateDir, $maildirDir);
        } catch (Throwable) {
            $message = 'IMAP sync configuration is invalid for this account.';
            $this->recordError($account, $message);

            return MbsyncResult::failed($message);
        }
        file_put_contents($temp->path(), $rendered);

        if (! BinaryProcess::available('mbsync')) {
            return MbsyncResult::unavailable();
        }

        $result = BinaryProcess::runCapture(['mbsync', '-c', $temp->path(), '-a'], self::RUN_TIMEOUT);
        // $temp is unlinked when it goes out of scope at the end of this method.

        if (! $result['ok']) {
            // Surface mbsync's OWN error (connection refused, TLS reject, auth
            // failure, …) so the owner can self-diagnose. mbsync never echoes the
            // IMAP password (fed via PassCmd), so the captured stream carries only
            // the owner's own host/IP + error text; run through Redactor anyway.
            $detail = self::tailDetail($result['err'] !== '' ? $result['err'] : $result['out']);
            $message = $detail !== ''
                ? 'IMAP sync failed: '.$detail
                : sprintf('IMAP sync failed (mbsync exit %s).', $result['exit'] ?? '?');
            $this->recordError($account, $message);

            return MbsyncResult::failed($message);
        }

        $account->forceFill([
            'status' => 'idle',
            'last_error' => null,
            'last_synced_at' => now(),
        ])->save();

        return MbsyncResult::success();
    }

    /**
     * Reduce mbsync's captured output to a short, single-line detail suitable
     * for a user-facing last_error: keep the last few non-empty lines, collapse
     * to one line, and hard-cap the length. Redaction happens in recordError().
     */
    private static function tailDetail(string $raw): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: []),
            static fn (string $l): bool => $l !== '',
        ));
        $detail = trim(implode(' — ', array_slice($lines, -3)));

        return mb_strlen($detail) > 400 ? mb_substr($detail, -400) : $detail;
    }

    private function recordError(MailAccount $account, string $message): void
    {
        $account->forceFill([
            'status' => 'error',
            'last_error' => Redactor::redact($message),
        ])->save();
    }

    /**
     * Durable directory where mbsync persists its UIDVALIDITY/UID sync-state
     * files across runs. Never wiped between runs — losing it only forces a
     * redundant full re-pull, never risks origin data (the config is pull-only).
     */
    private function stateDir(MailAccount $account): string
    {
        return storage_path('app/mail-sync/'.$account->id.'/state');
    }

    private function maildirDir(MailAccount $account): string
    {
        return self::maildirPathFor($account);
    }

    /**
     * The absolute Maildir root this account is mirrored into. Single source of
     * truth so the ingest producer enumerates the very tree mbsync wrote.
     */
    public static function maildirPathFor(MailAccount $account): string
    {
        return storage_path('app/mail-sync/'.$account->id.'/maildir');
    }
}
