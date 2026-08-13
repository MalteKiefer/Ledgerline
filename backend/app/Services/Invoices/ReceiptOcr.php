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

    /**
     * Hard cap on a rasterised page's longer edge (px). pdftoppm `-scale-to`
     * takes priority over `-r`, so a giant-MediaBox PDF can't render to a
     * multi-gigapixel bitmap (decompression-bomb / OOM DoS). 5000px bounds a page
     * to ≤~35 MPixel regardless of its declared physical size.
     */
    private const MAX_RASTER_PX = 5000;

    /**
     * Reject a decoded image above this pixel budget before handing it to
     * Leptonica/tesseract — a tiny file can declare enormous dimensions.
     */
    private const MAX_IMAGE_PIXELS = 40_000_000;

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
    public function extract(string $path, string $mime, string $lang, bool $forceOcr = false): array
    {
        if ($mime === self::PDF_MIME) {
            return $this->extractPdf($path, $lang, $forceOcr);
        }

        return ['text' => $this->ocrImage($path, $lang), 'source' => 'ocr', 'pages' => 1];
    }

    /** @return OcrResult */
    private function extractPdf(string $path, string $lang, bool $forceOcr = false): array
    {
        // A PDF whose embedded text layer is JUSTIFIED-mangled (e.g. debitoor bakes
        // spaces mid-word: "Softw are") is unusable — the caller can force rasterise+OCR
        // to read the glyphs directly and get clean words instead.
        if ($forceOcr) {
            return $this->ocrPdf($path, $lang);
        }

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
            // `-scale-to` bounds the output pixel dimensions (takes priority over
            // `-r`), so a giant-MediaBox page can't rasterise to a gigapixel bomb.
            BinaryProcess::run([
                'pdftoppm', '-png', '-r', (string) self::RASTER_DPI,
                '-scale-to', (string) self::MAX_RASTER_PX,
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
        // Guard against a decompression bomb before Leptonica decodes the whole
        // bitmap into memory (a tiny file can declare huge dimensions).
        if ($this->exceedsPixelBudget($path)) {
            return '';
        }

        // Default PSM (block layout) preserves lines; -l selects the language(s).
        $out = BinaryProcess::run(['tesseract', $path, 'stdout', '-l', $lang], self::TIMEOUT);

        return $out ?? '';
    }

    /**
     * True when the image's declared pixel count exceeds {@see MAX_IMAGE_PIXELS}.
     * Undeterminable dimensions (getimagesize returns false, e.g. some AVIF
     * builds) are allowed through — the pdftoppm `-scale-to` cap already bounds
     * the PDF path, and Leptonica applies its own size limits.
     */
    private function exceedsPixelBudget(string $path): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }
        $w = (int) $info[0];
        $h = (int) $info[1];

        return $w > 0 && $h > 0 && ($w * $h) > self::MAX_IMAGE_PIXELS;
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
