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

final class InvoiceRevisionMail extends Mailable
{
    use Queueable;

    /** @param array{name: string, address: string} $sender */
    public function __construct(
        public readonly string $messageId,
        public readonly string $number,
        public readonly string $pdfBytes,
        public readonly string $pdfFilename,
        public readonly array $sender,
        public readonly bool $reminder = false,
        public readonly string $customer = '',
        public readonly int $daysOverdue = 0,
        public readonly string $openAmount = '',
    ) {}

    public function headers(): Headers
    {
        return new Headers(messageId: trim($this->messageId, '<>'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->sender['address'], $this->sender['name']),
            subject: __($this->reminder ? 'invoices.invoice_reminder_subject' : 'invoices.email_subject', [
                'number' => $this->number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.finance-invoice', with: [
            'number' => $this->number,
            'company' => $this->sender['name'],
            'reminder' => $this->reminder,
            'customer' => $this->customer,
            'daysOverdue' => $this->daysOverdue,
            'openAmount' => $this->openAmount,
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
