<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Support\OutboundUrl;
use RuntimeException;

/**
 * Minimal raw-IMAP client for ONE operation: DELETE a message from the origin
 * mailbox by its RFC822 Message-ID. This is a deliberate, destructive
 * WRITE-to-origin — everything else in the mail archive except push-back is
 * strictly pull-only/read-only-origin. It runs only on an explicit, per-message
 * user action (the "delete from server" feature), behind a confirmation.
 *
 * There is no ext-imap in the runtime image, so this speaks just enough of the
 * IMAP wire protocol over a socket: greeting → optional STARTTLS → LOGIN →
 * SELECT → UID SEARCH HEADER Message-Id → UID STORE +FLAGS (\Deleted) →
 * UID EXPUNGE (fallback EXPUNGE) → LOGOUT.
 *
 * The IMAP host is validated with OutboundUrl::hostAllowed() (the same
 * SSRF/egress guard as sync/push-back) before any socket is opened. The
 * password is passed in already-decrypted and never logged. The Message-Id is a
 * non-secret routing identifier; it is used only to locate the message on the
 * origin and is not persisted or logged.
 */
class ImapDeleter
{
    private const CONNECT_TIMEOUT = 15;

    private const IO_TIMEOUT = 30;

    /** Monotonic command-tag counter for one delete() call. */
    private int $seq = 0;

    private function nextTag(): string
    {
        return 'd'.str_pad((string) (++$this->seq), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Delete the message with the given RFC822 Message-Id from $folder on the
     * account's origin IMAP server. Returns the number of messages expunged
     * (0 if none matched). Throws RuntimeException on any protocol/connection
     * failure (the caller maps that to a generic error — never leaks internals).
     */
    public function delete(MailAccount $account, string $folder, string $messageId, string $password): int
    {
        $host = (string) $account->host;
        if (! OutboundUrl::hostAllowed($host)) {
            throw new RuntimeException('mail delete refused: host not allowed');
        }
        $messageId = trim($messageId);
        if ($messageId === '') {
            throw new RuntimeException('mail delete: empty message-id');
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
            throw new RuntimeException('mail delete: cannot connect');
        }
        stream_set_timeout($sock, self::IO_TIMEOUT);

        try {
            $this->readGreeting($sock);

            if ($encryption === 'starttls') {
                $this->command($sock, 'STARTTLS');
                if (! stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('mail delete: STARTTLS failed');
                }
            }

            $this->command($sock, sprintf('LOGIN %s %s', $this->quoted((string) $account->username), $this->quoted($password)));
            $this->command($sock, sprintf('SELECT %s', $this->quoted($folder)));

            $uids = $this->searchByMessageId($sock, $messageId);
            if ($uids === []) {
                @fwrite($sock, "zzzz LOGOUT\r\n");

                return 0;
            }

            $set = implode(',', $uids);
            $this->command($sock, sprintf('UID STORE %s +FLAGS (\\Deleted)', $set));

            // Prefer UIDPLUS UID EXPUNGE (only our UIDs); fall back to EXPUNGE
            // (expunges all \Deleted in the folder — we only flagged ours).
            try {
                $this->command($sock, sprintf('UID EXPUNGE %s', $set));
            } catch (RuntimeException) {
                $this->command($sock, 'EXPUNGE');
            }

            @fwrite($sock, "zzzz LOGOUT\r\n");

            return count($uids);
        } finally {
            @fclose($sock);
            sodium_memzero($password);
        }
    }

    /**
     * Delete a batch of messages from $folder by their origin IMAP UIDs, in ONE
     * session (SELECT → UID STORE +FLAGS (\Deleted) → UID EXPUNGE). Used by the
     * "delete after import" sync path, where the ingestor already knows the exact
     * origin UIDs (from the mbsync Maildir filename) so no per-message search is
     * needed. Non-numeric UIDs are dropped. Returns the number of UIDs flagged
     * (0 if none valid). Throws on connection/protocol failure.
     *
     * @param  list<string>  $uids
     */
    public function deleteUids(MailAccount $account, string $folder, array $uids, string $password): int
    {
        $host = (string) $account->host;
        if (! OutboundUrl::hostAllowed($host)) {
            throw new RuntimeException('mail delete refused: host not allowed');
        }
        $clean = array_values(array_filter($uids, static fn ($u): bool => is_string($u) && ctype_digit($u)));
        if ($clean === []) {
            return 0;
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
            throw new RuntimeException('mail delete: cannot connect');
        }
        stream_set_timeout($sock, self::IO_TIMEOUT);

        try {
            $this->readGreeting($sock);

            if ($encryption === 'starttls') {
                $this->command($sock, 'STARTTLS');
                if (! stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('mail delete: STARTTLS failed');
                }
            }

            $this->command($sock, sprintf('LOGIN %s %s', $this->quoted((string) $account->username), $this->quoted($password)));
            $this->command($sock, sprintf('SELECT %s', $this->quoted($folder)));

            $set = implode(',', $clean);
            $this->command($sock, sprintf('UID STORE %s +FLAGS (\\Deleted)', $set));
            try {
                $this->command($sock, sprintf('UID EXPUNGE %s', $set));
            } catch (RuntimeException) {
                $this->command($sock, 'EXPUNGE');
            }

            @fwrite($sock, "zzzz LOGOUT\r\n");

            return count($clean);
        } finally {
            @fclose($sock);
            sodium_memzero($password);
        }
    }

    /**
     * UID SEARCH HEADER Message-Id "<id>"; parse the untagged "* SEARCH ..."
     * line into a list of UID strings (already validated numeric).
     *
     * @param  resource  $sock
     * @return list<string>
     */
    private function searchByMessageId($sock, string $messageId): array
    {
        $tag = $this->nextTag();
        $this->write($sock, sprintf("%s UID SEARCH HEADER Message-Id %s\r\n", $tag, $this->quoted($messageId)));

        $uids = [];
        while (true) {
            $line = $this->readLine($sock);
            if (preg_match('/^\* SEARCH\b(.*)$/i', $line, $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $tok) {
                    if ($tok !== '' && ctype_digit($tok)) {
                        $uids[] = $tok;
                    }
                }

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (! preg_match('/^'.preg_quote($tag, '/').' OK/i', $line)) {
                    throw new RuntimeException('mail delete: search failed');
                }

                return $uids;
            }
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
            throw new RuntimeException('mail delete: bad greeting');
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
                    throw new RuntimeException('mail delete: command failed');
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
            throw new RuntimeException('mail delete: write failed');
        }
    }

    /** @param resource $sock */
    private function readLine($sock): string
    {
        $line = @fgets($sock);
        $meta = stream_get_meta_data($sock);
        if ($line === false || ! empty($meta['timed_out'])) {
            throw new RuntimeException('mail delete: read timeout');
        }

        return rtrim($line, "\r\n");
    }
}
