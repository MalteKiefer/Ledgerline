<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Support\OutboundUrl;
use RuntimeException;

/**
 * Shared plumbing for the two raw-IMAP write-to-origin clients (ImapAppender +
 * ImapDeleter). These are the ONLY places the mail archive writes to an origin
 * mailbox — everything else is strictly pull-only/read-only-origin — and each
 * runs only on an explicit, per-message user action (or the opt-in
 * delete-after-import sync path). There is no ext-imap in the runtime image, so
 * this speaks just enough of the IMAP wire protocol over an {@see ImapStream}:
 * greeting → optional STARTTLS → LOGIN → (subclass op) → LOGOUT.
 *
 * The origin host is validated with OutboundUrl::hostAllowed() (the same
 * SSRF/egress guard as the sync path — link-local/metadata blocked) before any
 * socket is opened. The password is passed in already-decrypted and never
 * logged; subclasses sodium_memzero it in a finally block.
 *
 * `connect()` is overridable so the protocol logic can be exercised against a
 * scripted fake stream in tests without a real network socket.
 */
abstract class AbstractImapClient
{
    protected const CONNECT_TIMEOUT = 15;

    protected const IO_TIMEOUT = 30;

    /** Monotonic command-tag counter for one operation. */
    private int $seq = 0;

    /** Command-tag prefix, distinct per client for readable transcripts. */
    abstract protected function tagPrefix(): string;

    /** Refuse an SSRF/egress-blocked origin host before opening any socket. */
    protected function assertHostAllowed(MailAccount $account): void
    {
        if (! OutboundUrl::hostAllowed((string) $account->host)) {
            throw new RuntimeException('IMAP write-to-origin refused: host not allowed');
        }
    }

    /** Open a real socket connection to the account's origin IMAP server. */
    protected function connect(MailAccount $account): ImapStream
    {
        $encryption = (string) $account->encryption;
        $transport = in_array($encryption, ['ssl', 'tls'], true) ? 'ssl' : 'tcp';

        return ImapConnection::open(
            $transport,
            (string) $account->host,
            (int) $account->port,
            self::CONNECT_TIMEOUT,
            self::IO_TIMEOUT,
        );
    }

    /** Greeting → optional STARTTLS → LOGIN. */
    protected function login(ImapStream $conn, MailAccount $account, string $password): void
    {
        $this->readGreeting($conn);

        if ((string) $account->encryption === 'starttls') {
            $this->command($conn, 'STARTTLS');
            if (! $conn->enableCrypto()) {
                throw new RuntimeException('IMAP: STARTTLS failed');
            }
        }

        $this->command($conn, sprintf('LOGIN %s %s', $this->quoted((string) $account->username), $this->quoted($password)));
    }

    /** Best-effort logout; never throws. */
    protected function logout(ImapStream $conn): void
    {
        try {
            $conn->write("zzzz LOGOUT\r\n");
        } catch (RuntimeException) {
            // ignore — the connection is being closed anyway.
        }
    }

    protected function nextTag(): string
    {
        return $this->tagPrefix().str_pad((string) (++$this->seq), 4, '0', STR_PAD_LEFT);
    }

    /** The server greeting must be an untagged OK / PREAUTH. */
    protected function readGreeting(ImapStream $conn): void
    {
        if (preg_match('/^\* (OK|PREAUTH)/i', $conn->readLine()) !== 1) {
            throw new RuntimeException('IMAP: bad greeting');
        }
    }

    /** Send a tagged command and require a tagged OK. */
    protected function command(ImapStream $conn, string $command): void
    {
        $tag = $this->nextTag();
        $conn->write("{$tag} {$command}\r\n");
        $this->expectTagged($conn, $tag);
    }

    /** Read response lines until the one prefixed with $tag; require OK. */
    protected function expectTagged(ImapStream $conn, string $tag): void
    {
        while (true) {
            $line = $conn->readLine();
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('IMAP: command failed');
                }

                return;
            }
        }
    }

    /**
     * IMAP quoted-string: wrap in double quotes, escaping \ and ". An IMAP
     * quoted-string (RFC 3501) cannot carry CR/LF or other control characters,
     * so a value containing one (a folder / Message-Id / username / password with
     * an embedded newline) could break out of the quoted string and inject a
     * physical IMAP command line. FAIL CLOSED: refuse any NUL, C0 control (incl.
     * \r/\n) or DEL — mirrors MbsyncConfig::quote's control-char guard.
     */
    protected function quoted(string $s): string
    {
        if (preg_match('/[\x00-\x1f\x7f]/', $s) === 1) {
            throw new RuntimeException('IMAP: refusing a value with a control character');
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }
}
