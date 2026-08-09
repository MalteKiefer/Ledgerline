<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Support\OutboundUrl;
use App\Support\Redactor;
use Throwable;

/**
 * A minimal, dependency-free IMAP login probe used by the account
 * "test connection" endpoint (no ext-imap; a raw socket + LOGIN + LOGOUT).
 * SSRF-guarded (the host must pass OutboundUrl::hostAllowed before any socket
 * is opened), TLS-verified, short-timeout, and the plaintext password is
 * scrubbed on every exit path. Returns a small {ok, detail} verdict; the detail
 * is Redactor-scrubbed so no credential can leak into a response.
 *
 * Injected through the container so the test suite can bind a fake and never
 * touch the network (mirrors MbsyncRunner's fakeability).
 */
class ImapProbe
{
    private const TIMEOUT = 15;

    /**
     * Probe the account's IMAP login with its stored credentials.
     *
     * @return array{ok: bool, detail: string}
     */
    public function probe(MailAccount $account): array
    {
        $host = (string) $account->host;
        if (! OutboundUrl::hostAllowed($host)) {
            return ['ok' => false, 'detail' => 'The mail server host is not an allowed outbound destination.'];
        }

        $port = (int) $account->port;
        $encryption = (string) $account->encryption;
        $username = (string) $account->username;
        $password = (string) $account->password;

        $transport = ($encryption === 'ssl' || $encryption === 'tls') ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
        ]]);

        $stream = null;
        try {
            $errno = 0;
            $errstr = '';
            $stream = @stream_socket_client(
                sprintf('%s://%s:%d', $transport, $host, $port),
                $errno,
                $errstr,
                self::TIMEOUT,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if ($stream === false) {
                return ['ok' => false, 'detail' => $this->clean($errstr !== null && $errstr !== '' ? $errstr : 'Could not connect to the mail server.')];
            }
            stream_set_timeout($stream, self::TIMEOUT);

            $greeting = (string) fgets($stream);
            if (! str_contains($greeting, 'OK')) {
                return ['ok' => false, 'detail' => 'The mail server did not send a valid IMAP greeting.'];
            }

            if ($encryption === 'starttls') {
                fwrite($stream, "a0 STARTTLS\r\n");
                $resp = $this->readTagged($stream, 'a0');
                if (! str_contains($resp, 'a0 OK')) {
                    return ['ok' => false, 'detail' => 'The mail server refused STARTTLS.'];
                }
                if (! @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['ok' => false, 'detail' => 'TLS negotiation failed.'];
                }
            }

            fwrite($stream, sprintf("a1 LOGIN %s %s\r\n", $this->quote($username), $this->quote($password)));
            $login = $this->readTagged($stream, 'a1');
            fwrite($stream, "a2 LOGOUT\r\n");

            if (str_contains($login, 'a1 OK')) {
                return ['ok' => true, 'detail' => 'Connected and authenticated successfully.'];
            }

            return ['ok' => false, 'detail' => $this->clean($this->tail($login) ?: 'IMAP login was rejected.')];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $this->clean($e->getMessage())];
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
            // Scrub the plaintext password copy.
            if (function_exists('sodium_memzero')) {
                try {
                    sodium_memzero($password);
                } catch (Throwable) {
                    $password = '';
                }
            } else {
                $password = '';
            }
        }
    }

    /**
     * Read response lines until the tagged completion line for $tag appears (or
     * the stream ends / times out).
     *
     * @param  resource  $stream
     */
    private function readTagged($stream, string $tag): string
    {
        $buf = '';
        for ($i = 0; $i < 50; $i++) {
            $line = fgets($stream);
            if ($line === false) {
                break;
            }
            $buf .= $line;
            if (str_starts_with($line, $tag.' ')) {
                break;
            }
            $meta = stream_get_meta_data($stream);
            if ($meta['timed_out'] === true) {
                break;
            }
        }

        return $buf;
    }

    /** IMAP-quote a string literal (escape backslash + double-quote). */
    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /** The last non-empty line, single-line, capped. */
    private function tail(string $raw): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        $last = $lines === [] ? '' : (string) end($lines);

        return mb_substr($last, 0, 200);
    }

    private function clean(string $message): string
    {
        return mb_substr(Redactor::redact($message), 0, 200);
    }
}
