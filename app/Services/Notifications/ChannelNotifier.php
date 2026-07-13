<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\AppSettings;
use App\Support\OutboundUrl;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Generic multi-channel notifier (in-app/desktop bell, e-mail, NTFY, webhook)
 * driven by the global notification settings. One channel failing never stops
 * the others — failures are logged. Used for share/download/paperless notices;
 * backups have their own tailored notifier.
 */
class ChannelNotifier
{
    /**
     * @param  list<string>  $channels
     * @param  array{url?:?string, level?:string, category?:string, priority?:string, event?:string}  $opts
     */
    public function send(array $channels, string $title, string $body, array $opts = []): void
    {
        if ($channels === []) {
            return;
        }
        $settings = AppSettings::current();

        foreach (array_unique($channels) as $channel) {
            try {
                match ($channel) {
                    // In-app bell targets a specific user; skip when no owner is given.
                    'desktop' => isset($opts['user_id'])
                        ? AppNotification::record((int) $opts['user_id'], $opts['level'] ?? 'info', $title, $body ?: ($opts['url'] ?? null), $opts['category'] ?? 'reminder')
                        : null,
                    'ntfy' => $this->ntfy($settings, $title, $body, ['priority' => $opts['priority'] ?? 'default', 'click' => $opts['url'] ?? null]),
                    'webhook' => $this->webhook($settings, [
                        'event' => $opts['event'] ?? 'reminder',
                        'title' => $title,
                        'message' => $body,
                        'url' => $opts['url'] ?? null,
                    ]),
                    'mail' => $this->mail($settings, $title, $body),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Channel notification failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Low-level NTFY publish. Public so other notifiers (e.g. backups) reuse the
     * single hardened transport instead of duplicating it.
     *
     * @param  array{priority?:string, tags?:string, click?:?string}  $opts
     */
    public function ntfy(AppSettings $s, string $title, string $body, array $opts = []): void
    {
        if (! $s->ntfy_enabled || ! $s->ntfy_url || ! $s->ntfy_topic) {
            throw new \RuntimeException('NTFY is not enabled or not fully configured.');
        }
        $url = rtrim((string) $s->ntfy_url, '/').'/'.ltrim((string) $s->ntfy_topic, '/');
        // Strip CR/LF: header values are attacker-influenced (reminder title /
        // click URL) and a newline would smuggle extra headers.
        $headers = [
            'Title' => $this->headerSafe($title),
            'Priority' => $this->headerSafe((string) ($opts['priority'] ?? 'default')),
            'Tags' => $this->headerSafe((string) ($opts['tags'] ?? 'bell')),
        ];
        if (! empty($opts['click'])) {
            $headers['Click'] = $this->headerSafe((string) $opts['click']);
        }
        // Resolve + pin the verified IP (SSRF/DNS-rebinding safe), no redirects.
        $request = OutboundUrl::client($url, 15)->withHeaders($headers);
        if ($s->ntfy_token) {
            $request = $request->withToken((string) $s->ntfy_token);
        }
        $request->withBody($body ?: $title, 'text/plain')->post($url)->throw();
    }

    /**
     * Low-level webhook POST of an arbitrary JSON payload (HMAC-signed when a
     * secret is set). Public so other notifiers reuse the single transport.
     *
     * @param  array<string,mixed>  $payload
     */
    public function webhook(AppSettings $s, array $payload): void
    {
        if (! $s->webhook_enabled || ! $s->webhook_url) {
            throw new \RuntimeException('Webhook is not enabled or has no URL.');
        }
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json'];
        if ($s->webhook_secret) {
            $headers['X-Ledgerline-Signature'] = 'sha256='.hash_hmac('sha256', $body, (string) $s->webhook_secret);
        }
        // Resolve + pin the verified IP (SSRF/DNS-rebinding safe), no redirects.
        OutboundUrl::client((string) $s->webhook_url, 15)
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post((string) $s->webhook_url)
            ->throw();
    }

    /** Remove CR/LF (and NUL) so a value cannot be used to inject extra headers. */
    private function headerSafe(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    /** Low-level SMTP send to the configured from-address (self-notification). */
    public function mail(AppSettings $s, string $subject, string $body): void
    {
        $this->mailTo($s, (string) $s->smtp_from_address, $subject, $body);
    }

    /** Low-level SMTP send to an explicit recipient. */
    public function mailTo(AppSettings $s, string $to, string $subject, string $body): void
    {
        if (! $s->mail_enabled || ! $s->smtp_host || ! $s->smtp_from_address) {
            throw new \RuntimeException('Mail is not enabled or has no host / from address.');
        }
        // Egress-guard the SMTP host at send time (mirrors ntfy/webhook/backup):
        // refuse the cloud-metadata surface / hardened-blocked ranges even if a
        // host was persisted before the guard existed. Fails closed.
        if (! OutboundUrl::hostAllowed((string) $s->smtp_host)) {
            throw new \RuntimeException('Refusing to send mail to a disallowed SMTP host.');
        }
        $implicitTls = $s->smtp_encryption === 'ssl';
        $port = (int) ($s->smtp_port ?: ($implicitTls ? 465 : 587));
        $transport = new EsmtpTransport((string) $s->smtp_host, $port, $implicitTls);
        if ($s->smtp_encryption === 'tls') {
            $transport->setRequireTls(true);
        }
        if ($s->smtp_username) {
            $transport->setUsername((string) $s->smtp_username);
        }
        if ($s->smtp_password) {
            $transport->setPassword((string) $s->smtp_password);
        }

        $email = (new Email)
            ->from(new Address((string) $s->smtp_from_address, (string) ($s->smtp_from_name ?: 'Ledgerline')))
            ->to($to ?: (string) $s->smtp_from_address)
            ->subject($subject)
            ->text($body ?: $subject);

        (new Mailer($transport))->send($email);
    }

    /** Whether outgoing mail is configured (used to gate the mail-share option). */
    public static function mailConfigured(?AppSettings $s = null): bool
    {
        $s ??= AppSettings::current();

        return (bool) $s->mail_enabled && (bool) $s->smtp_host && (bool) $s->smtp_from_address;
    }
}
