<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\UserSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The invoice a customer receives by email: a short lang-driven body plus the
 * stored invoice PDF attached. The recipient is set by the caller
 * (Mail::to($to)->send(...)); the PDF path is server-owned (Invoice::pdf_path),
 * never client-supplied. Sent synchronously through the DB-configured SMTP
 * (AppServiceProvider::applyMailSettings bridges the AppSettings SMTP creds onto
 * config/mail), the same transport Fortify's notifications use.
 */
class InvoiceMail extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invoices.email_subject', ['number' => $this->number()]),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.invoice', with: [
            'number' => $this->number(),
            'company' => $this->companyName(),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $path = $this->invoice->pdf_path;
        if (! is_string($path) || $path === '') {
            return [];
        }
        $disk = config('files.disk');

        return [
            Attachment::fromStorageDisk(is_string($disk) ? $disk : 'files', $path)
                ->as($this->number().'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    private function number(): string
    {
        return is_string($this->invoice->number) && $this->invoice->number !== ''
            ? $this->invoice->number
            : (string) $this->invoice->id;
    }

    /** The owner's company name (printed on the invoice) for a friendly sign-off. */
    private function companyName(): string
    {
        $name = UserSetting::for((int) $this->invoice->user_id)->company_name;
        if (is_string($name) && $name !== '') {
            return $name;
        }
        $app = config('app.name', 'Ledgerline');

        return is_string($app) && $app !== '' ? $app : 'Ledgerline';
    }
}
