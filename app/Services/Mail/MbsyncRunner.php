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
 * shells). MaildirIngestor then seals + durably archives each fetched
 * message and shreds the plaintext Maildir copy — this class only owns the
 * fetch step, never touches the archive tables, and never mutates anything
 * on the origin IMAP server.
 *
 * Ordering, in `run()`:
 *   1. Egress-guard the configured host BEFORE doing anything else. A host
 *      that resolves to a link-local/metadata address is refused outright —
 *      no config is rendered, no directory is created, no process is
 *      spawned. This mirrors every other outbound-target guard in the app
 *      (Paperless/ntfy/webhook/backup/invoice-SMTP).
 *   2. Ensure the account's durable scratch directories exist (state +
 *      Maildir — both persist across runs so mbsync can do incremental
 *      syncs instead of re-pulling the whole mailbox every time).
 *   3. Render the config and write it to a transient temp file (RAII —
 *      DiskTempFile unlinks it when the local variable goes out of scope at
 *      the end of this method, on every return path including the early
 *      "binary unavailable" one). The config is generated even when mbsync
 *      itself is absent, so a caller inspecting logs can see what would have
 *      run.
 *   4. If the `mbsync` binary isn't installed (a dev/CI box without the
 *      deploy image's mail toolchain — the real binary lands only there, see
 *      the infra task), return Unavailable WITHOUT touching the account's
 *      status/last_error: that's an environmental gap, not an account fault.
 *   5. Shell `mbsync -c <tempconfig> -a` (all channels in the single-account
 *      config) via BinaryProcess (array-argv, no shell string). Non-null
 *      stdout = success; null = BinaryProcess's fail-closed contract (missing
 *      binary, non-zero exit, or an exception) — either way we cannot
 *      distinguish the cause from here, so the account is marked with a
 *      fixed, generic, already-safe (nothing secret in it) error message
 *      rather than guessing at or leaking mbsync's raw output.
 */
/*
 * Not `final`: the sync producer (App\Jobs\Mail\SyncMailAccount) resolves this
 * from the container, and the test suite binds a subclass fake over it to drive
 * the fetch step without the network. All production behaviour still lives here.
 */
class MbsyncRunner
{
    /** mbsync itself has no built-in timeout; bound the whole sync run. */
    private const RUN_TIMEOUT = 300;

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
        // encryption, or a control character that could inject config lines —
        // see MbsyncConfig). Catch it so the "every outcome is an
        // MbsyncResult" contract holds instead of an uncaught throw, and so
        // the account is marked errored rather than silently retried forever.
        // The caught message is one of MbsyncConfig's own fixed strings (it
        // never echoes the offending value), but recordError() redacts anyway
        // as defence in depth.
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
        // $temp is unlinked when it goes out of scope at the end of this
        // method (its destructor fires here), regardless of which branch
        // below is taken.

        if (! $result['ok']) {
            // Surface mbsync's OWN error (connection refused, TLS reject, auth
            // failure, host unreachable, …) so the owner can self-diagnose in
            // the UI/API instead of "check the host". mbsync never echoes the
            // IMAP password — it is fed to mbsync's auth via PassCmd's stdout,
            // never printed to mbsync's own stdout/stderr — so the captured
            // stream carries only the owner's own host/IP + error text. It is
            // still run through Redactor (recordError) as defence in depth.
            $detail = self::tailDetail((string) ($result['err'] !== '' ? $result['err'] : $result['out']));
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
     * for a user-facing last_error: keep the last few non-empty lines (the
     * actual failure — "Cannot connect …", "Login failed", etc. — is at the
     * tail), collapse to one line, and hard-cap the length. Redaction happens
     * separately in recordError().
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

    /**
     * Mark the account as errored with a redacted message. The message may now
     * include a tail of mbsync's OWN captured output (host/IP + error text) so
     * the owner can self-diagnose — mbsync never emits the IMAP password (it is
     * consumed via PassCmd, never echoed), so the stream carries no secret.
     * Redactor::redact() still runs as defence in depth (strips Bearer/token=/
     * key= patterns), keeping the "every last_error is redacted" invariant
     * blanket rather than per-caller judgment.
     */
    private function recordError(MailAccount $account, string $message): void
    {
        $account->forceFill([
            'status' => 'error',
            'last_error' => Redactor::redact($message),
        ])->save();
    }

    /**
     * Durable directory where mbsync persists its UIDVALIDITY/UID sync-state
     * files across runs. Never wiped between runs (unlike the rendered
     * config file itself) — losing it only forces a redundant full re-pull,
     * never risks origin data, since the whole config is pull-only.
     */
    private function stateDir(MailAccount $account): string
    {
        return storage_path('app/mail-sync/'.$account->id.'/state');
    }

    /**
     * Durable directory mbsync mirrors the mailbox into. MaildirIngestor
     * reads from here and shreds each message file once it is durably
     * archived (see MaildirIngestor's class docblock for that ordering).
     */
    private function maildirDir(MailAccount $account): string
    {
        return self::maildirPathFor($account);
    }

    /**
     * The absolute Maildir root this account is mirrored into. Exposed as the
     * single source of truth for that path so the ingest producer
     * (App\Jobs\Mail\SyncMailAccount) enumerates the very tree mbsync wrote,
     * with no duplicated path literal to drift out of sync.
     */
    public static function maildirPathFor(MailAccount $account): string
    {
        return storage_path('app/mail-sync/'.$account->id.'/maildir');
    }
}
