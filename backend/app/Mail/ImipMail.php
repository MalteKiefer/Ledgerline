<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An iMIP (RFC 6047) meeting message: a plain-text body plus the iCalendar
 * payload attached with the correct method (REQUEST/CANCEL/REPLY) so mail
 * clients surface Accept/Decline / update / removal.
 */
class ImipMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $bodyText,
        private readonly string $ics,
        private readonly string $method,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<pre style="font-family:inherit;white-space:pre-wrap">'.e($this->bodyText).'</pre>');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->ics, 'invite.ics')
                ->withMime('text/calendar; method='.$this->method.'; charset=UTF-8'),
        ];
    }
}
