<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Support\BinaryProcess;
use Throwable;

/**
 * Transient server-side OCR for finance receipts. Mirrors the gallery's
 * transient-cleartext pattern: the caller hands over a raw (decrypted) document
 * ONLY to derive text — nothing is persisted, cached, or logged. Everything runs
 * on trusted local binaries via array-argv processes (no shell string → no
 * injection), and every temp artefact is unlinked in a `finally` block even on
 * throw.
 *
 * The server returns ONLY line-structured text. Recognition (total/merchant/
 * date/VAT/…) lives client-side in shared/receipt-ocr.js so it stays identical
 * across web/iOS/Android and improvable without a server deploy.
 *
 * @phpstan-type OcrResult array{text: string, source: string, pages: int}
 */
// Not final: resolved via the container so tests can bind a fake double.
class ReceiptOcr
{
    /** Raster image types we OCR directly (Leptonica-decodable). */
    public const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/bmp', 'image/tiff', 'image/avif',
    ];

    public const PDF_MIME = 'application/pdf';

    /** A PDF text layer must yield more than this many non-whitespace chars to be trusted. */
    private const MIN_PDF_TEXT_CHARS = 8;

    /** Never rasterise+OCR more than this many PDF pages (CPU + memory guard). */
    private const MAX_PDF_PAGES = 30;

    private const RASTER_DPI = 200;

    private const TIMEOUT = 60;

    /**
     * Whether the OCR engine is installed. The endpoint gates on this so a
     * toolchain-less deploy answers 501 cleanly (clients degrade to manual entry)
     * rather than returning empty text.
     */
    public function available(): bool
    {
        return BinaryProcess::available('tesseract');
    }

    /**
     * Extract line-structured plain text from a receipt image or PDF.
     * `source` is 'pdf-text' (embedded text layer) or 'ocr' (rasterised/scanned).
     *
     * @return OcrResult
     */
    public function extract(string $path, string $mime, string $lang): array
    {
        if ($mime === self::PDF_MIME) {
            return $this->extractPdf($path, $lang);
        }

        return ['text' => $this->ocrImage($path, $lang), 'source' => 'ocr', 'pages' => 1];
    }

    /** @return OcrResult */
    private function extractPdf(string $path, string $lang): array
    {
        // 1) Embedded text layer first — fast, no OCR, most modern invoices have it.
        //    -layout preserves the visual line/column structure the client parser needs.
        $text = BinaryProcess::run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-'], self::TIMEOUT);
        if ($text !== null && $this->nonWhitespaceLength($text) > self::MIN_PDF_TEXT_CHARS) {
            return ['text' => $this->normalize($text), 'source' => 'pdf-text', 'pages' => $this->pdfTextPages($text)];
        }

        // 2) Scanned PDF → rasterise each page then OCR.
        return $this->ocrPdf($path, $lang);
    }

    /** @return OcrResult */
    private function ocrPdf(string $path, string $lang): array
    {
        $dir = sys_get_temp_dir().'/llocr_'.bin2hex(random_bytes(8));
        if (! @mkdir($dir, 0700) && ! is_dir($dir)) {
            return ['text' => '', 'source' => 'ocr', 'pages' => 0];
        }

        try {
            $prefix = $dir.'/page';
            BinaryProcess::run([
                'pdftoppm', '-png', '-r', (string) self::RASTER_DPI,
                '-f', '1', '-l', (string) self::MAX_PDF_PAGES, $path, $prefix,
            ], self::TIMEOUT);

            $pages = glob($prefix.'*.png') ?: [];
            sort($pages);

            $parts = [];
            foreach ($pages as $img) {
                $parts[] = $this->ocrImage($img, $lang);
            }

            return ['text' => $this->normalize(implode("\n", $parts)), 'source' => 'ocr', 'pages' => count($pages)];
        } finally {
            $this->removeDir($dir);
        }
    }

    private function ocrImage(string $path, string $lang): string
    {
        // Default PSM (block layout) preserves lines; -l selects the language(s).
        $out = BinaryProcess::run(['tesseract', $path, 'stdout', '-l', $lang], self::TIMEOUT);

        return $out ?? '';
    }

    /** Normalise line endings to \n (keeping line structure), strip form-feeds, trim. */
    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);

        return trim($text);
    }

    private function nonWhitespaceLength(string $text): int
    {
        return strlen((string) preg_replace('/\s+/u', '', $text));
    }

    /** Page count from pdftotext output: pages are separated by a form-feed. */
    private function pdfTextPages(string $text): int
    {
        return substr_count(rtrim($text, "\f"), "\f") + 1;
    }

    /** Best-effort recursive unlink of the transient raster directory. */
    private function removeDir(string $dir): void
    {
        try {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        } catch (Throwable) {
            // Never let cleanup surface an error over the request.
        }
    }
}
