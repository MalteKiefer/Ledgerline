<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\StoredFile;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use Symfony\Component\Process\Process;

/**
 * Extracts searchable plain text from a stored file for full-text search.
 * Text-ish files are read directly; PDFs use pdftotext (then OCR via ocrmypdf
 * when there is no text layer); images are OCR'd with tesseract. All external
 * tools run with array args (no shell), page/time caps, and the output is
 * truncated to bound the stored content.
 */
class FileTextExtractor
{
    /** Max characters of extracted text kept per file. */
    private const MAX_CHARS = 200_000;

    /** OCR languages (matches the tesseract packs installed in the image). */
    private const OCR_LANGS = 'eng+deu';

    /** Extract and return the text for a file (empty string when unsupported). */
    public function extract(StoredFile $file): string
    {
        $mime = (string) $file->mime;
        $name = strtolower((string) $file->name);
        $disk = BlobStore::disk();
        if (! $disk->exists('files/'.$file->blob)) {
            return '';
        }

        $textLike = str_starts_with($mime, 'text/')
            || in_array($mime, ['application/json', 'application/xml', 'text/csv'], true)
            || preg_match('/\.(txt|md|markdown|csv|log|json|xml|yml|yaml|ini|conf)$/', $name) === 1;

        if ($textLike) {
            return $this->clip((string) $disk->get('files/'.$file->blob));
        }

        $isPdf = $mime === 'application/pdf' || str_ends_with($name, '.pdf');
        $isImage = str_starts_with($mime, 'image/');
        if (! $isPdf && ! $isImage) {
            return '';
        }

        $local = DiskTempFile::pull($disk, 'files/'.$file->blob, 'lltxt');
        try {
            return $isPdf ? $this->fromPdf($local) : $this->fromImage($local);
        } finally {
            @unlink($local);
        }
    }

    private function fromPdf(string $path): string
    {
        // Text layer first (fast). Cap to the first 50 pages.
        $text = $this->run(['pdftotext', '-q', '-l', '50', $path, '-'], 120);

        // Scanned PDF (little/no text) → OCR to a sidecar with ocrmypdf.
        if (mb_strlen(trim($text)) < 32 && $this->hasTool('ocrmypdf')) {
            $sidecar = tempnam(sys_get_temp_dir(), 'llocr').'.txt';
            $outPdf = tempnam(sys_get_temp_dir(), 'llocr').'.pdf';
            try {
                $this->run([
                    'ocrmypdf', '--force-ocr', '--optimize', '0', '--sidecar', $sidecar,
                    '-l', self::OCR_LANGS, $path, $outPdf,
                ], 300);
                if (is_file($sidecar)) {
                    $text = (string) file_get_contents($sidecar);
                }
            } finally {
                @unlink($sidecar);
                @unlink($outPdf);
            }
        }

        return $this->clip($text);
    }

    private function fromImage(string $path): string
    {
        if (! $this->hasTool('tesseract')) {
            return '';
        }

        return $this->clip($this->run(['tesseract', $path, 'stdout', '-l', self::OCR_LANGS], 180));
    }

    /** Run a process and return stdout (empty string on failure). */
    private function run(array $command, int $timeout): string
    {
        $p = new Process($command);
        $p->setTimeout($timeout);
        try {
            $p->run();
        } catch (\Throwable) {
            return '';
        }

        return $p->isSuccessful() ? $p->getOutput() : '';
    }

    private function hasTool(string $tool): bool
    {
        $which = new Process(['which', $tool]);
        $which->run();

        return $which->isSuccessful();
    }

    private function clip(string $text): string
    {
        // Binary or invalid-UTF-8 input (e.g. a mis-typed file forced down the
        // text path) makes the /u regexes return null and would crash the job,
        // and Postgres rejects NUL bytes. Scrub to valid UTF-8 and drop NULs
        // first, and null-coalesce every regex so extraction can never fail.
        $text = str_replace("\0", '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $text = preg_replace('/\R+/u', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, self::MAX_CHARS);
    }
}
