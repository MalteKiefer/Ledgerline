<?php

declare(strict_types=1);

namespace App\Services\Mail;

use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sends a single outbound message over an account's own SMTP server using
 * Symfony's mailer/mime (bundled with Laravel), independent of the app's
 * default mail transport.
 *
 * The build/send split keeps message construction pure and unit-testable:
 * build() assembles a fully-formed Email (no I/O), and send() builds, ships it
 * over an EsmtpTransport, and returns the raw MIME so the caller can APPEND it
 * to the account's IMAP Sent folder and archive it.
 *
 * $cfg is the account's smtpConfig() array:
 *   host, port, encryption ('ssl'|'starttls'|'none'), username, password,
 *   from_name, from_email, reply_to.
 *
 * $msg keys:
 *   to (string[]), cc (string[]), bcc (string[]), subject (string),
 *   html (?string), text (?string),
 *   attachments (array of ['filename'=>, 'content'=> binary, 'mime'=>]),
 *   in_reply_to (?string message-id), references (?string),
 *   from_name (?string override), from_email (string).
 */
final class SmtpSender
{
    /**
     * Build, send, and return the raw MIME of the sent message.
     *
     * @param  array<string,mixed>  $cfg
     * @param  array<string,mixed>  $msg
     */
    public function send(array $cfg, array $msg): string
    {
        $email = $this->build($cfg, $msg);
        $transport = $this->transport($cfg);

        try {
            (new Mailer($transport))->send($email);
        } catch (Throwable $e) {
            throw new RuntimeException('SMTP send failed: '.$e->getMessage(), 0, $e);
        }

        // toString() reflects the fully-serialised message (Message-ID, Date,
        // etc. are populated during send) so it can be appended to Sent.
        return $email->toString();
    }

    /**
     * Assemble the MIME message. Pure (no network), so it is unit-testable.
     *
     * @param  array<string,mixed>  $cfg
     * @param  array<string,mixed>  $msg
     */
    public function build(array $cfg, array $msg): Email
    {
        $email = new Email;

        $fromEmail = (string) ($msg['from_email'] ?? $cfg['from_email'] ?? '');
        $fromName = (string) ($msg['from_name'] ?? $cfg['from_name'] ?? '');
        $email->from(new Address($fromEmail, $fromName));

        foreach ((array) ($msg['to'] ?? []) as $to) {
            $email->addTo((string) $to);
        }
        foreach ((array) ($msg['cc'] ?? []) as $cc) {
            $email->addCc((string) $cc);
        }
        foreach ((array) ($msg['bcc'] ?? []) as $bcc) {
            $email->addBcc((string) $bcc);
        }

        $replyTo = $cfg['reply_to'] ?? null;
        if (is_string($replyTo) && $replyTo !== '') {
            $email->replyTo($replyTo);
        }

        $email->subject((string) ($msg['subject'] ?? ''));

        $html = $msg['html'] ?? null;
        $text = $msg['text'] ?? null;

        if (is_string($html) && $html !== '') {
            $email->html($html);
        }

        if (is_string($text) && $text !== '') {
            $email->text($text);
        } elseif (is_string($html) && $html !== '') {
            // Derive a plaintext fallback so the message is never HTML-only.
            $email->text(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        foreach ((array) ($msg['attachments'] ?? []) as $attachment) {
            $email->attach(
                (string) ($attachment['content'] ?? ''),
                isset($attachment['filename']) ? (string) $attachment['filename'] : null,
                isset($attachment['mime']) ? (string) $attachment['mime'] : null,
            );
        }

        // Threading headers. Symfony forbids re-adding these, so guard on set.
        $headers = $email->getHeaders();

        $inReplyTo = $msg['in_reply_to'] ?? null;
        if (is_string($inReplyTo) && $inReplyTo !== '' && ! $headers->has('In-Reply-To')) {
            $headers->addTextHeader('In-Reply-To', $inReplyTo);
        }

        $references = $msg['references'] ?? null;
        if (is_string($references) && $references !== '' && ! $headers->has('References')) {
            $headers->addTextHeader('References', $references);
        }

        return $email;
    }

    /**
     * Build the SMTP transport for the given account config.
     *
     * Encryption maps to EsmtpTransport's $tls constructor arg:
     *   'ssl'      => true  (implicit/SMTPS, e.g. port 465)
     *   'starttls' => null  (auto: opportunistic STARTTLS, the default)
     *   'none'     => false (plaintext, no TLS)
     *
     * @param  array<string,mixed>  $cfg
     */
    private function transport(array $cfg): EsmtpTransport
    {
        $host = (string) ($cfg['host'] ?? 'localhost');
        $port = (int) ($cfg['port'] ?? 0);

        $encryption = (string) ($cfg['encryption'] ?? 'starttls');

        $tls = match ($encryption) {
            'ssl' => true,
            'none' => false,
            default => null, // 'starttls' and anything else: default STARTTLS
        };

        $transport = new EsmtpTransport($host, $port, $tls);

        // Opportunistic STARTTLS is strippable by a MITM (the server can simply
        // omit the STARTTLS capability), leaking credentials in cleartext.
        // Require TLS for every mode except implicit TLS ('ssl', already
        // encrypted from the first byte) and 'none', which is the deliberate
        // no-TLS escape hatch for trusted local relays.
        if ($encryption !== 'ssl' && $encryption !== 'none') {
            $transport->setRequireTls(true);
        }

        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');

        if ($username !== '') {
            $transport->setUsername($username);
        }
        if ($password !== '') {
            $transport->setPassword($password);
        }

        return $transport;
    }
}
