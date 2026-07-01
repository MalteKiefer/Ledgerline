<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a finalised invoice with its Factur-X PDF attached.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->invoice->type->label();

        return new Envelope(
            subject: "{$label} {$this->invoice->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice',
            with: ['invoice' => $this->invoice],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, ($this->invoice->number ?? 'invoice').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
