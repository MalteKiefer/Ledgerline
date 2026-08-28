<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use App\Models\UserSetting;
use App\Support\OutboundUrl;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use RuntimeException;

final class CompanySmtpMailer
{
    public function __construct(private readonly CompanyMailTransport $transport) {}

    public function configured(int $ownerId): bool
    {
        $settings = UserSetting::query()->find($ownerId);
        $host = $settings?->getAttribute('company_smtp_host');
        $from = $settings?->getAttribute('company_smtp_from_address');

        return $settings !== null
            && (bool) $settings->getAttribute('company_smtp_enabled')
            && is_string($host)
            && trim($host) !== ''
            && is_string($from)
            && trim($from) !== ''
            && OutboundUrl::hostAllowed(trim($host));
    }

    /** @return array{name: string, address: string} */
    public function senderIdentity(int $ownerId): array
    {
        $settings = UserSetting::query()->find($ownerId);
        $address = $settings?->getAttribute('company_smtp_from_address');
        $name = $settings?->getAttribute('company_smtp_from_name')
            ?? $settings?->getAttribute('company_name');

        return [
            'name' => is_string($name) ? $name : '',
            'address' => is_string($address) ? $address : '',
        ];
    }

    public function send(int $ownerId, string $recipient, Mailable $mail): void
    {
        $settings = UserSetting::query()->find($ownerId);
        if (! $this->configured($ownerId) || $settings === null) {
            throw new RuntimeException('quote_mail_smtp_unavailable');
        }

        $hostValue = $settings->getAttribute('company_smtp_host');
        $fromValue = $settings->getAttribute('company_smtp_from_address');
        $portValue = $settings->getAttribute('company_smtp_port');
        if (! is_string($hostValue) || ! is_string($fromValue)) {
            throw new RuntimeException('quote_mail_smtp_unavailable');
        }
        $host = trim($hostValue);
        $from = trim($fromValue);
        $port = is_int($portValue)
            ? $portValue
            : (is_string($portValue) && ctype_digit($portValue) ? (int) $portValue : 587);
        $mailerName = 'company_smtp_'.$ownerId.'_'.bin2hex(random_bytes(12));
        $encryption = $settings->getAttribute('company_smtp_encryption');
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'encryption' => is_string($encryption) && $encryption !== '' ? $encryption : null,
                'username' => $settings->getAttribute('company_smtp_username'),
                'password' => $settings->getAttribute('company_smtp_password'),
                'timeout' => 15,
            ],
            "mail.from.{$mailerName}" => [
                'address' => $from,
                'name' => $this->senderIdentity($ownerId)['name'] ?: $from,
            ],
        ]);

        try {
            $this->transport->send($mailerName, $recipient, $mail);
        } finally {
            $manager = app('mail.manager');
            if ($manager instanceof MailManager) {
                $manager->purge($mailerName);
            }
            config([
                "mail.mailers.{$mailerName}" => null,
                "mail.from.{$mailerName}" => null,
            ]);
        }
    }
}
