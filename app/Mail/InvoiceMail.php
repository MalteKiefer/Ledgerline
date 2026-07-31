<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BuildsInvoiceMail;
use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The invoice a customer receives by email: a short lang-driven body plus the
 * stored invoice PDF attached. The recipient is set by the caller
 * (Mail::to($to)->send(...)); the PDF path is server-owned (Invoice::pdf_path),
 * never client-supplied. Sent over the user's OWN company SMTP (the caller
 * selects the `company_smtp` runtime mailer); the From address is set here from
 * the company SMTP settings so it never falls back to the workspace
 * notification sender.
 */
class InvoiceMail extends Mailable
{
    use BuildsInvoiceMail;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->companyFrom($this->invoice),
            subject: __('invoices.email_subject', ['number' => $this->invoiceNumber($this->invoice)]),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.invoice', with: [
            'number' => $this->invoiceNumber($this->invoice),
            'company' => $this->companyName($this->invoice),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $attachment = $this->invoicePdfAttachment($this->invoice);

        return $attachment !== null ? [$attachment] : [];
    }
}
