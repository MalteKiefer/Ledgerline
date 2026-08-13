<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BuildsInvoiceMail;
use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * A payment reminder (Mahnung) the CUSTOMER receives about an overdue invoice —
 * distinct from InvoiceMail (which is the invoice itself). A lang-driven dunning
 * body with the level in the subject/body, plus the stored invoice PDF attached.
 * Sent over the user's OWN company SMTP (the caller selects the `company_smtp`
 * runtime mailer); the recipient is set by the caller (Mail::to($to)->send(...)).
 */
class InvoiceReminderMail extends Mailable
{
    use BuildsInvoiceMail;

    public function __construct(public Invoice $invoice, public int $level = 1) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->companyFrom($this->invoice),
            subject: __('invoices.dun_subject', [
                'number' => $this->invoiceNumber($this->invoice),
                'level' => $this->level,
            ]),
        );
    }

    public function content(): Content
    {
        $due = $this->invoice->due_date;
        $days = $due instanceof Carbon && $due->lt(Carbon::today()) ? (int) $due->diffInDays(Carbon::today()) : 0;

        return new Content(text: 'emails.invoice-reminder', with: [
            'number' => $this->invoiceNumber($this->invoice),
            'company' => $this->companyName($this->invoice),
            'level' => $this->level,
            'days' => $days,
            'due' => $due instanceof Carbon ? $due->format('Y-m-d') : '',
            'gross' => number_format((float) ($this->invoice->gross ?? 0), 2).' '.(is_string($this->invoice->currency) ? $this->invoice->currency : 'EUR'),
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
