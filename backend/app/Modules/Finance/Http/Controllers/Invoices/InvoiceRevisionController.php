<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Invoices;

use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class InvoiceRevisionController
{
    private const string MAX_REVISION_ID = '9223372036854775807';

    public function __invoke(Request $request, string $invoice, string $revision): StreamedResponse
    {
        $request->validate(['download' => ['sometimes', 'boolean']]);
        $revisionId = $this->revisionId($revision);
        $owner = $request->user();
        if (! $owner instanceof User) {
            abort(401);
        }
        $ownerId = (int) $owner->id;
        $invoiceRecord = InvoiceRecord::query()
            ->select('finance_invoices.*')
            ->join(
                'finance_document_series',
                'finance_document_series.id',
                '=',
                'finance_invoices.document_series_id',
            )
            ->where('finance_invoices.user_id', $ownerId)
            ->where('finance_document_series.user_id', $ownerId)
            ->where('finance_invoices.uuid', $invoice)
            ->where('finance_document_series.document_type', 'invoice')
            ->firstOrFail();

        $document = DocumentRevisionRecord::query()
            ->where('user_id', $ownerId)
            ->where('document_series_id', (int) $invoiceRecord->document_series_id)
            ->whereKey($revisionId)
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
        $disposition = HeaderUtils::makeDisposition(
            $request->boolean('download') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $this->filename($document),
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

        if (! is_string($bytes)
            || ! str_starts_with($bytes, '%PDF-')
            || ! hash_equals($sha256, hash('sha256', $bytes))) {
            abort(404);
        }

        return $bytes;
    }

    private function filename(DocumentRevisionRecord $revision): string
    {
        $snapshot = $revision->getAttribute('snapshot');
        $number = is_array($snapshot) ? ($snapshot['document_number'] ?? null) : null;
        $safeNumber = is_string($number)
            ? trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $number), '.-_')
            : '';

        return ($safeNumber !== '' ? $safeNumber : 'invoice')
            .'-R'.max(1, (int) $revision->revision_number).'.pdf';
    }

    private function disk(): Filesystem
    {
        $name = config('files.disk');
        if (! is_string($name) || $name === '') {
            abort(404);
        }

        return Storage::disk($name);
    }

    private function revisionId(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1
            || strlen($value) > strlen(self::MAX_REVISION_ID)
            || (strlen($value) === strlen(self::MAX_REVISION_ID)
                && strcmp($value, self::MAX_REVISION_ID) > 0)) {
            abort(404);
        }

        return (int) $value;
    }
}
