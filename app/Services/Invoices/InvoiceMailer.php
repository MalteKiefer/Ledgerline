<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\UserSetting;
use App\Support\OutboundUrl;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends an invoice PDF by e-mail through the USER'S OWN dedicated invoice SMTP
 * server (separate from the workspace notification SMTP). The PDF is generated
 * client-side and passed in transiently — nothing is persisted or logged here
 * (the invoice content stays ZK at rest; this is a deliberate, user-initiated
 * boundary crossing, like sending a receipt to Paperless). The SMTP host is
 * egress-guarded and credentials live encrypted on UserSetting.
 */
class InvoiceMailer
{
    /** True when the user has configured + enabled their invoice SMTP server. */
    public function configured(UserSetting $s): bool
    {
        return (bool) $s->invoice_mail_enabled
            && (string) $s->invoice_smtp_host !== ''
            && (string) $s->invoice_from_email !== '';
    }

    /**
     * @param  string  $pdf  raw PDF bytes (transient)
     * @param  string  $filename  attachment filename
     *
     * @throws \RuntimeException on misconfiguration / disallowed host
     */
    public function send(UserSetting $s, string $to, string $subject, string $body, string $pdf, string $filename): void
    {
        if (! $this->configured($s)) {
            throw new \RuntimeException('Invoice mail is not configured.');
        }
        // Fail-closed egress guard on the SMTP host (mirrors ntfy/webhook/backup).
        if (! OutboundUrl::hostAllowed((string) $s->invoice_smtp_host)) {
            throw new \RuntimeException('Refusing to send to a disallowed SMTP host.');
        }

        $implicitTls = $s->invoice_smtp_encryption === 'ssl';
        $port = (int) ($s->invoice_smtp_port ?: ($implicitTls ? 465 : 587));
        $transport = new EsmtpTransport((string) $s->invoice_smtp_host, $port, $implicitTls);
        if ($s->invoice_smtp_encryption === 'tls') {
            $transport->setRequireTls(true);
        }
        if ($s->invoice_smtp_username) {
            $transport->setUsername((string) $s->invoice_smtp_username);
        }
        if ($s->invoice_smtp_password) {
            $transport->setPassword((string) $s->invoice_smtp_password);
        }

        $email = (new Email)
            ->from(new Address((string) $s->invoice_from_email, (string) ($s->invoice_from_name ?: 'Ledgerline')))
            ->to($to)
            ->subject($subject !== '' ? $subject : 'Invoice')
            ->text($body !== '' ? $body : $subject)
            ->attach($pdf, $filename !== '' ? $filename : 'invoice.pdf', 'application/pdf');

        (new Mailer($transport))->send($email);
    }
}
