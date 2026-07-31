<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\FileEntry;
use App\Support\BinaryProcess;
use App\Support\DiskTempFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extracts searchable plaintext from a stored file so the server can rebuild
 * the content search the pivot dropped (was client CLIP/OCR).
 *
 * Strategy by MIME:
 *   - text/* + markdown/csv/json/xml/yaml → read the bytes directly (capped).
 *   - application/pdf → poppler `pdftotext -layout` (embedded text layer).
 *   - image/*         → `tesseract` OCR (deu+eng), the same binaries the image
 *                       ships for receipt OCR.
 *   - anything else   → null (not indexable).
 *
 * Robustness is the contract: ANY failure (missing binary, unreadable bytes,
 * decode error, oversized file) returns null and is swallowed — indexing must
 * never throw into the caller. All binaries run via array-argv BinaryProcess
 * (no shell string → no injection) against a RAII temp file that is unlinked
 * even on throw.
 */
final class FileTextIndex
{
    /** Hard cap on the stored search_text (bytes). Keeps the column + index sane. */
    public const MAX_TEXT_BYTES = 1_048_576; // 1 MiB

    /** Cap on plaintext bytes read from disk before extraction. */
    private const READ_CAP = 2_097_152; // 2 MiB

    /** Never OCR / rasterise a binary larger than this (CPU + memory guard). */
    private const MAX_BINARY_BYTES = 26_214_400; // 25 MiB

    private const TIMEOUT = 60;

    private const OCR_LANG = 'deu+eng';

    /** Non-text/* MIME types we still treat as readable plaintext. */
    private const TEXT_MIMES = [
        'application/json',
        'application/xml',
        'application/x-yaml',
        'application/yaml',
        'application/javascript',
        'application/x-ndjson',
    ];

    /** Map an image MIME to a temp-file extension so the OCR binary sniffs it right. */
    private const IMAGE_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tif',
    ];

    /**
     * Derive searchable text for a file, or null if it isn't indexable / fails.
     */
    public function extract(FileEntry $file): ?string
    {
        try {
            $mime = strtolower((string) $file->mime);
            $path = $file->storage_path;
            $disk = Storage::disk($this->diskName());

            if ($path === '' || ! $disk->exists($path)) {
                return null;
            }

            if ($this->isTextMime($mime)) {
                return $this->cap($this->readPlainText($path));
            }

            if ($mime === 'application/pdf') {
                return $this->cap($this->extractPdf($path, $file->size));
            }

            if (str_starts_with($mime, 'image/')) {
                return $this->cap($this->extractImage($path, $mime, $file->size));
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function diskName(): string
    {
        $disk = config('files.disk');

        return is_string($disk) ? $disk : 'files';
    }

    private function isTextMime(string $mime): bool
    {
        return str_starts_with($mime, 'text/') || in_array($mime, self::TEXT_MIMES, true);
    }

    /** Read up to READ_CAP bytes of a text file from the disk. */
    private function readPlainText(string $path): ?string
    {
        $stream = Storage::disk($this->diskName())->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        try {
            $bytes = stream_get_contents($stream, self::READ_CAP);
        } finally {
            fclose($stream);
        }

        return is_string($bytes) ? $this->sanitize($bytes) : null;
    }

    private function extractPdf(string $path, int $size): ?string
    {
        if (! BinaryProcess::available('pdftotext')) {
            return null;
        }
        $tmp = $this->stage($path, 'pdf', $size);
        if ($tmp === null) {
            return null;
        }

        // -layout keeps the visual line/column structure; extract the whole file.
        $text = BinaryProcess::run(['pdftotext', '-layout', '-enc', 'UTF-8', $tmp->path(), '-'], self::TIMEOUT);

        return $text === null ? null : $this->sanitize($text);
        // $tmp destructs here → temp file unlinked (even had run() thrown).
    }

    private function extractImage(string $path, string $mime, int $size): ?string
    {
        if (! BinaryProcess::available('tesseract')) {
            return null;
        }
        $ext = self::IMAGE_EXT[$mime] ?? null;
        if ($ext === null) {
            return null;
        }
        $tmp = $this->stage($path, $ext, $size);
        if ($tmp === null) {
            return null;
        }

        $text = BinaryProcess::run(['tesseract', $tmp->path(), 'stdout', '-l', self::OCR_LANG], self::TIMEOUT);

        return $text === null ? null : $this->sanitize($text);
    }

    /**
     * Copy the (possibly remote, e.g. S3) bytes down to a local RAII temp file
     * with the right extension so a local binary can read them. Returns null if
     * the file is too big to OCR or the bytes can't be streamed.
     */
    private function stage(string $path, string $ext, int $size): ?DiskTempFile
    {
        if ($size > self::MAX_BINARY_BYTES) {
            return null;
        }
        $stream = Storage::disk($this->diskName())->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        $tmp = DiskTempFile::create('llfidx')->withExtension($ext);
        $out = fopen($tmp->path(), 'wb');
        if ($out === false) {
            fclose($stream);

            return null;
        }

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
            fclose($stream);
        }

        return $tmp;
    }

    /** Force valid UTF-8, normalise line endings, trim; empty → null. */
    private function sanitize(string $text): ?string
    {
        $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $clean = str_replace(["\r\n", "\r", "\f"], "\n", $clean);
        $clean = trim($clean);

        return $clean === '' ? null : $clean;
    }

    /** Cap stored text on a UTF-8 boundary so the column/index stay bounded. */
    private function cap(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        if (strlen($text) > self::MAX_TEXT_BYTES) {
            $text = mb_strcut($text, 0, self::MAX_TEXT_BYTES, 'UTF-8');
        }

        return $text;
    }
}
