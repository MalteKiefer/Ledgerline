<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Support\OutboundUrl;
use App\Support\Redactor;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * A dependency-light SMTP login probe for the account "test connection"
 * endpoint (parity with {@see ImapProbe}). Uses Symfony's EsmtpTransport to
 * connect + EHLO + (STARTTLS) + AUTH LOGIN and then immediately disconnect — it
 * never sends a message. SSRF-guarded (the host must pass
 * OutboundUrl::hostAllowed before any socket), short-timeout, and the verdict
 * detail is Redactor-scrubbed so no credential can leak into a response.
 *
 * Injected through the container so the test suite can bind a fake and never
 * touch the network (mirrors ImapProbe/MbsyncRunner fakeability). Returns a
 * small {ok, detail} verdict; never throws.
 */
class SmtpProbe
{
    private const TIMEOUT = 15;

    /**
     * Probe the account's SMTP login with its stored SMTP credentials.
     *
     * @return array{ok: bool, detail: string}
     */
    public function probe(MailAccount $account): array
    {
        $host = is_string($account->smtp_host) ? trim($account->smtp_host) : '';
        if ($host === '') {
            return ['ok' => false, 'detail' => 'No SMTP host is configured.'];
        }
        if (! OutboundUrl::hostAllowed($host)) {
            return ['ok' => false, 'detail' => 'The SMTP server host is not an allowed outbound destination.'];
        }

        $enc = is_string($account->smtp_encryption) ? $account->smtp_encryption : '';
        $port = is_int($account->smtp_port) && $account->smtp_port > 0 ? $account->smtp_port : 587;
        if (! OutboundUrl::mailPortAllowed($port)) {
            return ['ok' => false, 'detail' => 'The SMTP server port is not an allowed submission port.'];
        }
        // ssl/tls → implicit TLS on connect; starttls → opportunistic upgrade
        // (tls=false lets EsmtpTransport STARTTLS if the server advertises it).
        $implicitTls = $enc === 'ssl' || $enc === 'tls';

        // Pin the resolved IP: dial the literal address and keep TLS verification
        // bound to the hostname via peer_name — closes the resolve-then-reconnect
        // (DNS-rebind/TOCTOU) window that a bare hostname would leave open.
        try {
            $target = OutboundUrl::resolvedSocketTarget($host);
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $this->clean($e->getMessage())];
        }

        $transport = new EsmtpTransport($target['ip'], $port, $implicitTls);
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setStreamOptions(['ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $target['host'],
            ]]);
        }
        if (method_exists($stream, 'setTimeout')) {
            $stream->setTimeout(self::TIMEOUT);
        }
        $username = is_string($account->smtp_username) ? $account->smtp_username : '';
        if ($username !== '') {
            $transport->setUsername($username);
            $transport->setPassword((string) $account->smtp_password);
        }

        try {
            $transport->start();

            return ['ok' => true, 'detail' => 'Connected and authenticated successfully.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $this->clean($e->getMessage())];
        } finally {
            try {
                $transport->stop();
            } catch (Throwable) {
                // ignore — best-effort teardown.
            }
        }
    }

    private function clean(string $message): string
    {
        return mb_substr(Redactor::redact($message), 0, 200);
    }
}
