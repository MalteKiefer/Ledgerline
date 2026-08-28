<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Models\User;
use App\Modules\Finance\Http\Requests\Quotes\QuoteRevisionPdfRequest;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class QuoteRevisionPdfController
{
    public function __invoke(
        QuoteRevisionPdfRequest $request,
        string $quote,
        int $revision,
    ): StreamedResponse {
        $owner = $request->user();
        if (! $owner instanceof User) {
            abort(401);
        }
        $ownerId = $owner->id;
        $series = QuoteSeriesRecord::query()
            ->select('finance_quote_series.*')
            ->join(
                'finance_document_series',
                'finance_document_series.id',
                '=',
                'finance_quote_series.document_series_id',
            )
            ->where('finance_quote_series.user_id', $ownerId)
            ->where('finance_document_series.user_id', $ownerId)
            ->where('finance_document_series.uuid', $quote)
            ->where('finance_document_series.document_type', 'quote')
            ->firstOrFail();

        $document = DocumentRevisionRecord::query()
            ->where('user_id', $ownerId)
            ->where('document_series_id', (int) $series->document_series_id)
            ->where('id', $revision)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->firstOrFail();

        $path = $document->getAttribute('pdf_path');
        $sha256 = $document->getAttribute('pdf_sha256');
        if (! is_string($path)
            || ! is_string($sha256)
            || preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1
            || preg_match('#\Afinance/revisions/([a-f0-9]{2})/([a-f0-9]{64})\.pdf\z#D', $path, $matches) !== 1
            || $matches[1] !== substr($matches[2], 0, 2)) {
            abort(404);
        }

        $bytes = $this->verifiedBytes($path, $sha256);
        $filename = $this->filename($document->getAttribute('snapshot'));
        $disposition = HeaderUtils::makeDisposition(
            $request->wantsDownload() ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
        );

        return response()->stream(
            static function () use ($bytes): void {
                echo $bytes;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition,
                'Content-Length' => (string) strlen($bytes),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => 'private, max-age=31536000, immutable',
                'ETag' => '"'.$sha256.'"',
            ],
        );
    }

    private function verifiedBytes(string $path, string $sha256): string
    {
        try {
            $disk = $this->disk();
            if (! $disk->exists($path)) {
                abort(404);
            }
            $bytes = $disk->get($path);
        } catch (Throwable) {
            abort(404);
        }

        if (! is_string($bytes)) {
            abort(404);
        }

        if (! str_starts_with($bytes, '%PDF-')
            || ! hash_equals($sha256, hash('sha256', $bytes))) {
            abort(404);
        }

        return $bytes;
    }

    private function filename(mixed $snapshot): string
    {
        $label = is_array($snapshot) ? ($snapshot['revision_label'] ?? null) : null;
        $safeLabel = is_string($label)
            ? trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $label), '.-_')
            : '';

        return ($safeLabel !== '' ? $safeLabel : 'quote-revision').'.pdf';
    }

    private function disk(): Filesystem
    {
        $name = config('files.disk');
        if (! is_string($name) || $name === '') {
            abort(404);
        }

        return Storage::disk($name);
    }
}
