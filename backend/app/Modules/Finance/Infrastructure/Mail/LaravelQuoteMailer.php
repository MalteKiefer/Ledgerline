<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\Ports\Quotes\QuoteMailer;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Infrastructure\Mail\Jobs\DeliverQuoteRevision;
use Illuminate\Support\Facades\Storage;

final readonly class LaravelQuoteMailer implements QuoteMailer
{
    public function __construct(private CompanySmtpMailer $smtp) {}

    public function assertConfigured(int $ownerId): void
    {
        if (! $this->smtp->configured($ownerId)) {
            throw new InvalidQuoteAction('no_smtp');
        }
    }

    public function assertRevisionReady(QuoteRevisionRef $revision): void
    {
        $path = $revision->pdfPath;
        $sha256 = $revision->pdfSha256;
        if ($revision->status !== 'published'
            || $revision->publishedAt === null
            || ! is_string($path)
            || preg_match('#\Afinance/revisions/[0-9a-f]{2}/[0-9a-f]{64}\.pdf\z#D', $path) !== 1
            || ! is_string($sha256)
            || preg_match('/\A[0-9a-f]{64}\z/D', $sha256) !== 1) {
            throw new InvalidQuoteAction('no_pdf');
        }

        $diskName = config('files.disk');
        $disk = Storage::disk(is_string($diskName) ? $diskName : 'files');
        if (! $disk->exists($path)) {
            throw new InvalidQuoteAction('no_pdf');
        }
        $bytes = $disk->get($path);
        if (! is_string($bytes)
            || ! str_starts_with($bytes, '%PDF-')
            || ! hash_equals($sha256, hash('sha256', $bytes))) {
            throw new InvalidQuoteAction('no_pdf');
        }
    }

    public function dispatch(int $ownerId, int $deliveryId): void
    {
        DeliverQuoteRevision::dispatch($ownerId, $deliveryId)->afterCommit();
    }
}
