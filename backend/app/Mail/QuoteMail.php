<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\FinanceQuote;
use App\Models\UserSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * A quote on its way to the customer, with its PDF attached.
 *
 * The sender is the user's own company identity, not the workspace notification
 * address: a quote arrives from the business, and a reply has to land somewhere
 * a human reads.
 */
class QuoteMail extends Mailable
{
    use Queueable;

    public function __construct(public FinanceQuote $quote) {}

    public function envelope(): Envelope
    {
        $settings = UserSetting::for((int) $this->quote->user_id);
        $fromAddress = is_string($settings->company_smtp_from_address) && $settings->company_smtp_from_address !== ''
            ? $settings->company_smtp_from_address
            : null;
        $fromName = is_string($settings->company_smtp_from_name) && $settings->company_smtp_from_name !== ''
            ? $settings->company_smtp_from_name
            : (is_string($settings->company_name) ? $settings->company_name : null);

        return new Envelope(
            from: $fromAddress !== null ? new Address($fromAddress, $fromName) : null,
            subject: __('invoices.quote_mail_subject', ['number' => (string) ($this->quote->number ?? '')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.quote',
            with: [
                'number' => (string) ($this->quote->number ?? ''),
                'company' => $this->companyName(),
                // Spelled out in the body: a validity date the reader has to
                // open the attachment to find is a date they will miss.
                'valid' => $this->quote->valid_until?->format('d.m.Y') ?? '—',
            ],
        );
    }

    private function companyName(): string
    {
        $name = UserSetting::for((int) $this->quote->user_id)->company_name;

        return is_string($name) && $name !== '' ? $name : '';
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->quote->pdf_path;
        // Only the server-generated prefix, and only if the bytes are really
        // there: a mail that silently arrives without its quote is worse than
        // one that fails to send.
        if (! is_string($path) || ! str_starts_with($path, 'invoices/') || str_contains($path, '..')) {
            return [];
        }
        $diskName = config('files.disk');
        $disk = Storage::disk(is_string($diskName) ? $diskName : 'files');
        if (! $disk->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(is_string($diskName) ? $diskName : 'files', $path)
                ->as(($this->quote->number ?? 'quote').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
