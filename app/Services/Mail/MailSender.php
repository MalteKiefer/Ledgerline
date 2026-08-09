<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\ComposedMail;
use App\Models\MailAccount;
use App\Support\Mail\ImapAppender;
use App\Support\OutboundUrl;
use App\Support\Redactor;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends a user-composed message over the account's OWN SMTP transport, then
 * appends the sent copy back to the origin Sent folder (over IMAP). Mirrors the
 * company-SMTP invoice mailer pattern: a per-account runtime mailer is
 * configured from the encrypted SMTP credentials, used once, and torn down (its
 * config key is cleared and the password copy is scrubbed) in a finally block —
 * the credential never lingers in the merged config.
 *
 * The SMTP host is egress-guarded with OutboundUrl::hostAllowed() (the same
 * SSRF/metadata block as ImapAppender/ImapProbe/company SMTP) before the mailer
 * is built. The only outbound is the user's own SMTP server (send) + their own
 * IMAP server (the best-effort Sent append) — no new third-party egress.
 */
class MailSender
{
    public function __construct(private ImapAppender $appender) {}

    /**
     * Send $message via $account's SMTP and append the sent copy to the origin
     * Sent folder (best-effort). Throws RuntimeException when the account is not
     * SMTP-configured or the SMTP host is egress-blocked (the caller maps that to
     * a 422/generic error). The generated Message-Id is stamped onto the message.
     */
    public function send(MailAccount $account, ComposedMessage $message): SendResult
    {
        if (! $account->hasSmtp()) {
            throw new RuntimeException('mail send: account has no SMTP configuration');
        }

        $host = trim((string) $account->smtp_host);
        if (! OutboundUrl::hostAllowed($host)) {
            throw new RuntimeException('mail send: SMTP host not allowed');
        }
        // Refuse a "mail host" pointed at a non-mail port (SSRF pivot). NOTE: the
        // send path runs through Laravel's config-driven mailer (kept so Mail::fake
        // can intercept it in tests), which — unlike the raw IMAP sockets and the
        // SmtpProbe transport — offers no CURLOPT_RESOLVE-style IP pin, so the
        // hostAllowed() resolved-IP check + this port allowlist are its guard. Same
        // residual applies to the finance companyMailer, which shares this pattern.
        $smtpPort = is_int($account->smtp_port) && $account->smtp_port > 0 ? $account->smtp_port : 587;
        if (! OutboundUrl::mailPortAllowed($smtpPort)) {
            throw new RuntimeException('mail send: SMTP port not allowed');
        }

        $fromEmail = trim((string) $account->from_email);
        $fromName = is_string($account->from_name) && trim($account->from_name) !== '' ? trim($account->from_name) : null;

        // Stamp a Message-Id so the sent copy is identifiable + threadable and so
        // the API can return it. Symfony's IdentificationHeader wraps the value in
        // angle brackets and validates the addr-spec, so the header value is
        // UNBRACKETED (uuid@domain); the API returns the bracketed <uuid@domain>.
        $domain = Str::after($fromEmail, '@');
        $messageIdLocal = Str::uuid()->toString().'@'.($domain !== '' ? $domain : 'localhost');

        $composed = new ComposedMessage(
            subject: $message->subject,
            text: $message->text,
            html: $message->html,
            fromEmail: $fromEmail,
            fromName: $fromName,
            to: $message->to,
            cc: $message->cc,
            bcc: $message->bcc,
            messageId: $messageIdLocal,
            inReplyTo: $message->inReplyTo,
            references: $message->references,
            attachments: $message->attachments,
            sentFolder: $message->sentFolder,
        );

        $mailerName = 'mail_send_'.$account->id;
        $password = (string) $account->smtp_password;

        try {
            $this->configureMailer($mailerName, $account, $host, $fromEmail, $fromName, $password);

            $sent = Mail::mailer($mailerName)->send(new ComposedMail($composed));

            $appended = $this->appendToSent($account, $composed->sentFolder, $sent instanceof SentMessage ? $sent : null);

            return new SendResult('<'.$messageIdLocal.'>', $appended);
        } finally {
            // Tear the runtime mailer down so the SMTP password does not linger in
            // the merged config, and scrub the local copy.
            config(["mail.mailers.{$mailerName}" => null, "mail.from.{$mailerName}" => null]);
            if ($password !== '' && function_exists('sodium_memzero')) {
                sodium_memzero($password);
            }
        }
    }

    /** Wire a one-shot SMTP runtime mailer from the account's encrypted creds. */
    protected function configureMailer(string $name, MailAccount $account, string $host, string $fromEmail, ?string $fromName, string $password): void
    {
        // 'ssl'/'tls' → implicit TLS; 'starttls' → opportunistic upgrade (Symfony
        // sets the scheme from the 'encryption' value); 'none' → no encryption.
        $enc = is_string($account->smtp_encryption) && $account->smtp_encryption !== '' ? $account->smtp_encryption : null;
        $encryption = match ($enc) {
            'ssl', 'tls' => 'tls',
            'starttls' => 'tls',
            default => null,
        };

        config([
            "mail.mailers.{$name}" => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => is_int($account->smtp_port) && $account->smtp_port > 0 ? $account->smtp_port : 587,
                'encryption' => $encryption,
                'username' => is_string($account->smtp_username) && $account->smtp_username !== '' ? $account->smtp_username : null,
                'password' => $password !== '' ? $password : null,
                'timeout' => 15,
            ],
            "mail.from.{$name}" => [
                'address' => $fromEmail,
                'name' => $fromName ?? $fromEmail,
            ],
        ]);
    }

    /**
     * APPEND the just-sent raw message to the origin Sent folder, flagged \Seen.
     * Best-effort: a null SentMessage (e.g. under Mail::fake) or any
     * IMAP/connection failure is logged (secret-free) and swallowed — a failed
     * Sent copy must never fail the send.
     */
    private function appendToSent(MailAccount $account, string $sentFolder, ?SentMessage $sent): bool
    {
        if ($sent === null) {
            return false;
        }

        try {
            $raw = $sent->getSymfonySentMessage()->getOriginalMessage()->toString();
            if ($raw === '') {
                return false;
            }
            $this->appender->append($account, $sentFolder, $raw, (string) $account->password, seen: true);

            return true;
        } catch (\Throwable $e) {
            Log::warning('mail.send.sent_append_failed', ['account_id' => $account->id, 'error' => Redactor::redact($e->getMessage())]);

            return false;
        }
    }
}
