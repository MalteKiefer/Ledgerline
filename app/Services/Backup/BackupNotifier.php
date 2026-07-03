<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\AppSettings;
use App\Models\BackupJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends a backup success/failure notification over the channel chosen for the
 * job (NTFY, generic webhook or e-mail), using the global settings for that
 * channel. Notification failures never fail the backup — they are logged only.
 */
class BackupNotifier
{
    public function notify(BackupJob $job, bool $success, string $summary): void
    {
        if ($job->notify === 'none') {
            return;
        }

        $settings = AppSettings::current();
        $title = sprintf('[Ledgerline] Backup %s: %s', $success ? 'OK' : 'FAILED', $job->name);

        try {
            match ($job->notify) {
                'ntfy' => $this->ntfy($settings, $title, $summary, $success),
                'webhook' => $this->webhook($settings, $job, $success, $summary),
                'mail' => $this->mail($settings, $title, $summary),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('Backup notification failed', ['job' => $job->id, 'channel' => $job->notify, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send a test message over one channel. Unlike notify(), this throws on any
     * failure (misconfiguration, unreachable server) so the caller can surface
     * the reason — it is triggered by an explicit "send test" action.
     */
    public function test(string $channel): void
    {
        $settings = AppSettings::current();
        $title = '[Ledgerline] Test notification';
        $body = 'This is a test message from Ledgerline. If you can read this, the channel works.';

        match ($channel) {
            'ntfy' => $this->ntfy($settings, $title, $body, true),
            'webhook' => $this->sendWebhook($settings, [
                'event' => 'test',
                'status' => 'success',
                'message' => $body,
            ]),
            'mail' => $this->mail($settings, $title, $body),
            default => throw new \InvalidArgumentException('Unknown notification channel.'),
        };
    }

    private function ntfy(AppSettings $s, string $title, string $body, bool $success): void
    {
        if (! $s->ntfy_enabled || ! $s->ntfy_url || ! $s->ntfy_topic) {
            throw new \RuntimeException('NTFY is not enabled or not fully configured.');
        }
        $url = rtrim((string) $s->ntfy_url, '/').'/'.ltrim((string) $s->ntfy_topic, '/');
        $request = Http::withHeaders([
            'Title' => $title,
            'Priority' => $success ? 'default' : 'high',
            'Tags' => $success ? 'white_check_mark' : 'rotating_light',
        ]);
        if ($s->ntfy_token) {
            $request = $request->withToken((string) $s->ntfy_token);
        }
        $request->withBody($body, 'text/plain')->post($url)->throw();
    }

    private function webhook(AppSettings $s, BackupJob $job, bool $success, string $summary): void
    {
        $this->sendWebhook($s, [
            'event' => 'backup',
            'job' => $job->name,
            'source' => $job->source,
            'status' => $success ? 'success' : 'failed',
            'message' => $summary,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function sendWebhook(AppSettings $s, array $data): void
    {
        if (! $s->webhook_enabled || ! $s->webhook_url) {
            throw new \RuntimeException('Webhook is not enabled or has no URL.');
        }
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json'];
        if ($s->webhook_secret) {
            $headers['X-Ledgerline-Signature'] = 'sha256='.hash_hmac('sha256', $payload, (string) $s->webhook_secret);
        }
        Http::withHeaders($headers)->withBody($payload, 'application/json')->post((string) $s->webhook_url)->throw();
    }

    private function mail(AppSettings $s, string $subject, string $body): void
    {
        if (! $s->mail_enabled || ! $s->smtp_host || ! $s->smtp_from_address) {
            throw new \RuntimeException('Mail is not enabled or has no host / from address.');
        }
        // 'ssl' = implicit TLS (SMTPS, port 465). 'tls' = STARTTLS (port 587):
        // enforce it via setRequireTls so credentials never go out in cleartext
        // if the server fails to advertise STARTTLS.
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
            ->to((string) $s->smtp_from_address)
            ->subject($subject)
            ->text($body);

        (new Mailer($transport))->send($email);
    }
}
