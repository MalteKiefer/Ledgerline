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
final class BackupNotifier
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

    private function ntfy(AppSettings $s, string $title, string $body, bool $success): void
    {
        if (! $s->ntfy_enabled || ! $s->ntfy_url || ! $s->ntfy_topic) {
            return;
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
        if (! $s->webhook_enabled || ! $s->webhook_url) {
            return;
        }
        $payload = json_encode([
            'event' => 'backup',
            'job' => $job->name,
            'source' => $job->source,
            'status' => $success ? 'success' : 'failed',
            'message' => $summary,
        ], JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json'];
        if ($s->webhook_secret) {
            $headers['X-Ledgerline-Signature'] = 'sha256='.hash_hmac('sha256', $payload, (string) $s->webhook_secret);
        }
        Http::withHeaders($headers)->withBody($payload, 'application/json')->post((string) $s->webhook_url)->throw();
    }

    private function mail(AppSettings $s, string $subject, string $body): void
    {
        if (! $s->mail_enabled || ! $s->smtp_host || ! $s->smtp_from_address) {
            return;
        }
        $tls = $s->smtp_encryption === 'ssl';
        $transport = new EsmtpTransport((string) $s->smtp_host, (int) ($s->smtp_port ?: ($tls ? 465 : 587)), $tls);
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
