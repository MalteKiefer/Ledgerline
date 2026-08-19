<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Mail\ComposedMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Symfony\Component\Mime\Email;

/**
 * A user-composed outbound message (compose / reply / forward). The bodies are
 * the user's OWN content and MUST NOT be run through Blade — a Blade-rendered
 * user string would be a server-side template-injection (RCE) foot-gun. So the
 * plain-text body is set directly on the Symfony message (Email::text()) and the
 * HTML body — when present — is passed as a raw htmlString (an HtmlString, used
 * verbatim, never compiled). Threading headers (In-Reply-To / References) and a
 * generated Message-Id come from the ComposedMessage.
 *
 * Sent over the account's OWN SMTP runtime mailer (see MailSender), never the
 * workspace notification transport.
 */
class ComposedMail extends Mailable
{
    public function __construct(private ComposedMessage $composed) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->composed->fromEmail, $this->composed->fromName ?? ''),
            to: $this->addresses($this->composed->to),
            cc: $this->addresses($this->composed->cc),
            bcc: $this->addresses($this->composed->bcc),
            subject: $this->composed->subject,
        );
    }

    public function headers(): Headers
    {
        $text = [];
        if ($this->composed->inReplyTo !== null && $this->composed->inReplyTo !== '') {
            $text['In-Reply-To'] = $this->composed->inReplyTo;
        }

        return new Headers(
            messageId: $this->composed->messageId,
            references: $this->composed->references,
            text: $text,
        );
    }

    /**
     * Content must define at least an html part for buildView(); the HTML body
     * (when present) is a raw htmlString. For a text-only message we emit a
     * placeholder html part here and strip it again in build() so the wire
     * message is a clean text/plain — the real bodies are set in build().
     */
    public function content(): Content
    {
        $html = $this->composed->html;

        return new Content(htmlString: $html !== null && $html !== '' ? $html : '<x/>');
    }

    public function build(): self
    {
        return $this->withSymfonyMessage(function (Email $email): void {
            if ($this->composed->text !== null) {
                $email->text($this->composed->text, 'utf-8');
            }
            // Text-only: drop the placeholder html part so the message is a pure
            // text/plain (buildView requires an html part to exist first).
            if ($this->composed->html === null || $this->composed->html === '') {
                $email->html(null);
            }
            if ($this->composed->readReceipt) {
                $email->getHeaders()->addTextHeader('Disposition-Notification-To', $this->composed->fromEmail);
            }
            if ($this->composed->highPriority) {
                $email->getHeaders()->addTextHeader('X-Priority', '1 (Highest)');
                $email->getHeaders()->addTextHeader('Importance', 'high');
            }
        });
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->composed->attachments as $att) {
            $out[] = Attachment::fromData(static fn (): string => $att['bytes'], $att['filename'])
                ->withMime($att['mime']);
        }

        return $out;
    }

    /**
     * @param  list<array{name:?string, email:string}>  $list
     * @return list<Address>
     */
    private function addresses(array $list): array
    {
        return array_map(
            static fn (array $a): Address => new Address($a['email'], $a['name'] ?? ''),
            $list
        );
    }
}
