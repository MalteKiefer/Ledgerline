<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\FinanceReceipt;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Files raw document bytes as a standalone receipt.
 *
 * One owner for "bytes -> receipt", because two callers need it — the mail
 * reader's "save to finance" and the ingest rule that does it automatically —
 * and a second copy would eventually dedup differently from this one.
 *
 * Deduplicated by the same content signature the receipt inbox uses, so the same
 * invoice arriving twice (a resend, a rule and a manual save racing) stays one
 * receipt. Everything downstream — OCR, amount/date/merchant recognition,
 * partner matching, matching against a bank booking — is the existing chain and
 * happens without this knowing about it.
 */
final class ReceiptFiler
{
    /**
     * What a receipt can be: a PDF or a raster scan. Never svg/html — those are
     * stored-XSS vectors, and this mirrors what the upload endpoint accepts.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        'image/heic', 'image/heif', 'image/gif',
    ];

    /**
     * @return array{receipt_id: int, duplicate: bool}|null null when the type is
     *                                                      not a receipt or the write failed
     */
    public function file(int $userId, string $bytes, ?string $filename, ?string $mime): ?array
    {
        $mime = strtolower(trim((string) $mime));
        if (! in_array($mime, self::ALLOWED, true)) {
            return null;
        }

        $sig = strlen($bytes).':'.hash('sha256', $bytes);
        $existing = FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $userId)->where('sig', $sig)->first();
        if ($existing instanceof FinanceReceipt) {
            return ['receipt_id' => (int) $existing->id, 'duplicate' => true];
        }

        $path = 'invoices/'.Str::uuid()->toString();
        if (BlobStore::disk()->put($path, $bytes) === false) {
            return null;
        }

        $receipt = new FinanceReceipt;
        $receipt->forceFill([
            'user_id' => $userId,
            'name' => $this->safeName($filename),
            'kind' => 'receipt',
            'blob_path' => $path,
            'mime' => $mime,
            'size' => strlen($bytes),
            'sig' => $sig,
        ])->save();

        return ['receipt_id' => (int) $receipt->id, 'duplicate' => false];
    }

    /** A filename that came from a mail header: keep the basename, nothing else. */
    private function safeName(?string $name): string
    {
        $base = basename(str_replace('\\', '/', (string) $name));
        $base = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $base) ?? '');

        return $base !== '' ? mb_substr($base, 0, 300) : 'receipt';
    }
}
