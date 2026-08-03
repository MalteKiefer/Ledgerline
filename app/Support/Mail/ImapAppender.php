<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Support\OutboundUrl;
use RuntimeException;

/**
 * Minimal raw-IMAP client for ONE operation: APPEND a message back to the
 * origin mailbox (the "push back to server" feature). This is the single place
 * that deliberately WRITES to an origin mailbox — everything else in the mail
 * archive is strictly pull-only/read-only-origin. It is used only on an
 * explicit, per-message user action.
 *
 * There is no ext-imap in the runtime image, so this speaks just enough of the
 * IMAP wire protocol over a socket: greeting → optional STARTTLS → LOGIN →
 * APPEND (with a literal) → LOGOUT. It intentionally implements nothing else.
 *
 * The IMAP host is validated with OutboundUrl::hostAllowed() (the same
 * SSRF/egress guard as the sync path — link-local/metadata blocked) before any
 * socket is opened. The password is passed in already-decrypted and is never
 * logged.
 */
class ImapAppender
{
    private const CONNECT_TIMEOUT = 15;

    private const IO_TIMEOUT = 30;

    /** Monotonic command-tag counter for one append() call. */
    private int $seq = 0;

    private function nextTag(): string
    {
        return 'a'.str_pad((string) (++$this->seq), 4, '0', STR_PAD_LEFT);
    }

    /**
     * APPEND $rawMessage (a full RFC822 message) to $folder on the account's
     * origin IMAP server. Throws RuntimeException on any protocol/connection
     * failure (the caller maps that to a generic error — never leaks internals).
     */
    public function append(MailAccount $account, string $folder, string $rawMessage, string $password): void
    {
        $host = (string) $account->host;
        if (! OutboundUrl::hostAllowed($host)) {
            throw new RuntimeException('mail push-back refused: host not allowed');
        }
        if ($rawMessage === '') {
            throw new RuntimeException('mail push-back: empty message');
        }

        $encryption = (string) $account->encryption;
        $port = (int) $account->port;
        $implicitTls = in_array($encryption, ['ssl', 'tls'], true);
        $transport = $implicitTls ? 'ssl' : 'tcp';

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            "{$transport}://{$host}:{$port}",
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $ctx,
        );
        if ($sock === false) {
            throw new RuntimeException('mail push-back: cannot connect');
        }
        stream_set_timeout($sock, self::IO_TIMEOUT);

        try {
            $this->readGreeting($sock);

            if ($encryption === 'starttls') {
                $this->command($sock, 'STARTTLS');
                if (! stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('mail push-back: STARTTLS failed');
                }
            }

            $this->command($sock, sprintf('LOGIN %s %s', $this->quoted((string) $account->username), $this->quoted($password)));
            $this->appendLiteral($sock, $folder, $rawMessage);

            // Best-effort logout; ignore its result.
            @fwrite($sock, "zzzz LOGOUT\r\n");
        } finally {
            @fclose($sock);
            sodium_memzero($password);
        }
    }

    /**
     * Read the server greeting; must be untagged OK / PREAUTH.
     *
     * @param  resource  $sock
     */
    private function readGreeting($sock): void
    {
        $line = $this->readLine($sock);
        if (! preg_match('/^\* (OK|PREAUTH)/i', $line)) {
            throw new RuntimeException('mail push-back: bad greeting');
        }
    }

    /**
     * Send a tagged command and read until its tagged response; throws unless
     * the tagged line is OK.
     *
     * @param  resource  $sock
     */
    private function command($sock, string $command): void
    {
        $tag = $this->nextTag();
        $this->write($sock, "{$tag} {$command}\r\n");
        $this->expectTagged($sock, $tag);
    }

    /**
     * APPEND with a literal: send the command, wait for the "+" continuation,
     * stream the exact-length message, then read the tagged OK.
     *
     * @param  resource  $sock
     */
    private function appendLiteral($sock, string $folder, string $rawMessage): void
    {
        $tag = $this->nextTag();
        $len = strlen($rawMessage);
        $this->write($sock, sprintf("%s APPEND %s {%d}\r\n", $tag, $this->quoted($folder), $len));

        $cont = $this->readLine($sock);
        if (! str_starts_with($cont, '+')) {
            throw new RuntimeException('mail push-back: server rejected APPEND');
        }
        $this->write($sock, $rawMessage."\r\n");
        $this->expectTagged($sock, $tag);
    }

    /**
     * Read response lines until the one prefixed with $tag; require OK.
     *
     * @param  resource  $sock
     */
    private function expectTagged($sock, string $tag): void
    {
        while (true) {
            $line = $this->readLine($sock);
            if (str_starts_with($line, $tag.' ')) {
                if (! preg_match('/^'.preg_quote($tag, '/').' OK/i', $line)) {
                    throw new RuntimeException('mail push-back: command failed');
                }

                return;
            }
        }
    }

    /** IMAP quoted-string: wrap in double quotes, escaping \ and ". */
    private function quoted(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }

    /** @param resource $sock */
    private function write($sock, string $data): void
    {
        if (@fwrite($sock, $data) === false) {
            throw new RuntimeException('mail push-back: write failed');
        }
    }

    /** @param resource $sock */
    private function readLine($sock): string
    {
        $line = @fgets($sock);
        $meta = stream_get_meta_data($sock);
        if ($line === false || ! empty($meta['timed_out'])) {
            throw new RuntimeException('mail push-back: read timeout');
        }

        return rtrim($line, "\r\n");
    }
}
