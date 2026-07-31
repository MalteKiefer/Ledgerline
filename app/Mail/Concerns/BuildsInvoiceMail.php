<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Invoice;
use App\Models\UserSetting;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;

/**
 * Shared pieces for the customer-facing invoice mailables (InvoiceMail +
 * InvoiceReminderMail): the From identity comes from the user's OWN company SMTP
 * settings (deliberately independent of the workspace notification sender), and
 * the stored invoice PDF (server-owned pdf_path, never client-supplied) is
 * attached. Both mailables are sent over the `company_smtp` runtime mailer.
 */
trait BuildsInvoiceMail
{
    /** The From address from the company SMTP settings (null if not set). */
    protected function companyFrom(Invoice $invoice): ?Address
    {
        $s = UserSetting::for((int) $invoice->user_id);
        $from = is_string($s->company_smtp_from_address) && trim($s->company_smtp_from_address) !== ''
            ? trim($s->company_smtp_from_address)
            : null;
        if ($from === null) {
            return null;
        }
        $name = is_string($s->company_smtp_from_name) && $s->company_smtp_from_name !== ''
            ? $s->company_smtp_from_name
            : $this->companyName($invoice);

        return new Address($from, $name);
    }

    /** The stored invoice PDF as an attachment (empty when none is stored). */
    protected function invoicePdfAttachment(Invoice $invoice): ?Attachment
    {
        $path = $invoice->pdf_path;
        if (! is_string($path) || $path === '') {
            return null;
        }
        $disk = config('files.disk');

        return Attachment::fromStorageDisk(is_string($disk) ? $disk : 'files', $path)
            ->as($this->invoiceNumber($invoice).'.pdf')
            ->withMime('application/pdf');
    }

    /** The invoice number (falls back to the id for an unnumbered draft). */
    protected function invoiceNumber(Invoice $invoice): string
    {
        return is_string($invoice->number) && $invoice->number !== ''
            ? $invoice->number
            : (string) $invoice->id;
    }

    /** The owner's company name (printed on the invoice) for a friendly sign-off. */
    protected function companyName(Invoice $invoice): string
    {
        $name = UserSetting::for((int) $invoice->user_id)->company_name;
        if (is_string($name) && $name !== '') {
            return $name;
        }
        $app = config('app.name', 'Ledgerline');

        return is_string($app) && $app !== '' ? $app : 'Ledgerline';
    }
}
