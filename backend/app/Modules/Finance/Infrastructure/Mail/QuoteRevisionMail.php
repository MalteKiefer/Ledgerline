<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

final class QuoteRevisionMail extends Mailable
{
    use Queueable;

    /** @param array{name: string, address: string} $sender */
    public function __construct(
        public readonly string $messageId,
        public readonly string $number,
        public readonly string $revisionLabel,
        public readonly string $validUntil,
        public readonly string $pdfBytes,
        public readonly string $pdfFilename,
        public readonly array $sender,
    ) {}

    public function headers(): Headers
    {
        return new Headers(messageId: trim($this->messageId, '<>'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->sender['address'], $this->sender['name']),
            subject: __('invoices.quote_mail_subject', ['number' => $this->revisionLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.finance-quote', with: [
            'number' => $this->revisionLabel,
            'company' => $this->sender['name'],
            'valid' => $this->validUntil,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBytes, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
