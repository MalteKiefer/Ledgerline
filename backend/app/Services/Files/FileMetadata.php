<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\FileEntry;
use App\Support\BinaryProcess;
use App\Support\DiskTempFile;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Extract per-filetype metadata for the Files info panel: image EXIF, PDF info,
 * audio/video technical data (all via exiftool — one robust reader across formats),
 * STL geometry (PHP), and text/archive stats (PHP). Runs ONLY on the worker (the
 * observer dispatches it), never on the web request — the same untrusted-decode
 * posture as the gallery/OCR toolchain. Best-effort: every failure yields null; the
 * result is a flat, sanitised string map (label => value) plus a `kind` for grouping.
 *
 * @phpstan-type Meta array{kind: string, fields: array<string, string>}
 */
class FileMetadata
{
    private const TIMEOUT = 30;

    /** Skip files bigger than this (exiftool reads headers, but cap the stage copy). */
    private const MAX_BYTES = 512 * 1024 * 1024;

    /** Cap a single value's length so a runaway tag can't bloat the row. */
    private const VALUE_CAP = 300;

    /**
     * exiftool tags worth showing, in display order. exiftool emits a superset;
     * we curate to keep the panel clean and avoid surfacing odd/binary tags.
     *
     * @var list<string>
     */
    private const EXIF_KEYS = [
        'ImageWidth', 'ImageHeight', 'ImageSize', 'Megapixels', 'ColorSpace', 'Orientation',
        'Make', 'Model', 'LensModel', 'FNumber', 'ExposureTime', 'ISO', 'FocalLength', 'Flash',
        'DateTimeOriginal', 'CreateDate', 'GPSLatitude', 'GPSLongitude', 'GPSAltitude',
        'Duration', 'AudioBitrate', 'AudioSampleRate', 'AudioChannels', 'VideoFrameRate',
        'AvgBitrate', 'CompressorName', 'Artist', 'Album', 'Title', 'Genre', 'Year', 'Track',
        'Author', 'Creator', 'Producer', 'PageCount', 'PDFVersion', 'Encryption', 'Words', 'Pages',
        'FileType', 'Compression', 'BitsPerSample', 'SampleRate', 'Channels',
    ];

    /**
     * @return Meta|null
     */
    public function extract(FileEntry $file): ?array
    {
        try {
            $mime = strtolower((string) $file->mime);
            $name = strtolower((string) $file->name);
            $path = (string) $file->storage_path;
            $size = (int) $file->size;
            $disk = Storage::disk($this->diskName());
            if ($path === '' || $size > self::MAX_BYTES || ! $disk->exists($path)) {
                return null;
            }
            $kind = $this->kind($mime, $name);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $ext = $ext !== '' ? $ext : 'bin';

            // Text stats read the stream directly — no local staging needed.
            if ($kind === 'text') {
                $fields = $this->textStats($path);

                return $fields === [] ? null : ['kind' => 'text', 'fields' => $fields];
            }

            $tmp = $this->stage($path, $ext, $size);
            if ($tmp === null) {
                return null;
            }

            // STL geometry is exiftool's blind spot — parse it ourselves.
            if ($kind === 'model') {
                $fields = $this->stl($tmp->path());

                return $fields === [] ? null : ['kind' => 'model', 'fields' => $fields];
            }
            if ($kind === 'archive') {
                $fields = $this->zip($tmp->path(), 'archive');

                return $fields === [] ? null : ['kind' => 'archive', 'fields' => $fields];
            }

            // image / pdf / audio / video / office / other → exiftool; office also
            // gets its zip stats (entry count + word count).
            $fields = BinaryProcess::available('exiftool') ? $this->exif($tmp->path()) : [];
            if ($kind === 'office') {
                $fields = $this->zip($tmp->path(), 'office') + $fields;
            }

            return $fields === [] ? null : ['kind' => $kind, 'fields' => $fields];
        } catch (Throwable) {
            return null;
        }
    }

    private function kind(string $mime, string $name): string
    {
        if (str_ends_with($name, '.stl') || $mime === 'model/stl' || $mime === 'application/sla') {
            return 'model';
        }
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_contains($mime, 'officedocument') || str_contains($mime, 'opendocument')
            || preg_match('/\.(docx|xlsx|pptx|odt|ods|odp)$/', $name) === 1) {
            return 'office';
        }
        if ($mime === 'application/zip' || preg_match('/\.(zip|jar)$/', $name) === 1) {
            return 'archive';
        }
        if (str_starts_with($mime, 'text/') || preg_match('/\.(txt|md|csv|log|json|xml|ya?ml)$/', $name) === 1) {
            return 'text';
        }

        return 'other';
    }

    /**
     * @return array<string, string>
     */
    private function exif(string $localPath): array
    {
        $json = BinaryProcess::run(['exiftool', '-json', '-n', '-fast2', $localPath], self::TIMEOUT);
        if ($json === null) {
            return [];
        }
        $decoded = json_decode($json, true);
        $row = is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : null;
        if ($row === null) {
            return [];
        }
        $out = [];
        foreach (self::EXIF_KEYS as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = $this->scalar($row[$key]);
            if ($v !== null) {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /**
     * Parse an STL (binary or ASCII): triangle count + bounding-box dimensions.
     *
     * @return array<string, string>
     */
    private function stl(string $localPath): array
    {
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return [];
        }
        try {
            $head = fread($fh, 84);
            if ($head === false || strlen($head) < 84) {
                return [];
            }
            $fileSize = filesize($localPath) ?: 0;
            $u = unpack('V', substr($head, 80, 4));
            $count = is_array($u) ? (int) $this->num($u[1] ?? 0) : 0;
            $binary = ($fileSize === 84 + $count * 50) && $count > 0;

            $minX = $minY = $minZ = INF;
            $maxX = $maxY = $maxZ = -INF;
            $tris = 0;
            if ($binary) {
                $tris = $count;
                // Cap the geometry scan so a huge model can't stall the worker.
                $scan = min($count, 200000);
                for ($i = 0; $i < $scan; $i++) {
                    $rec = fread($fh, 50);
                    if ($rec === false || strlen($rec) < 50) {
                        break;
                    }
                    // 12 bytes normal, then 3 vertices of 12 bytes (3 floats each).
                    for ($v = 0; $v < 3; $v++) {
                        $off = 12 + $v * 12;
                        $xyz = unpack('g3', substr($rec, $off, 12));
                        if (! is_array($xyz)) {
                            continue;
                        }
                        $x = $this->num($xyz[1]);
                        $y = $this->num($xyz[2]);
                        $z = $this->num($xyz[3]);
                        $minX = min($minX, $x);
                        $maxX = max($maxX, $x);
                        $minY = min($minY, $y);
                        $maxY = max($maxY, $y);
                        $minZ = min($minZ, $z);
                        $maxZ = max($maxZ, $z);
                    }
                }
            } else {
                rewind($fh);
                $ascii = fread($fh, max(1, min($fileSize, 8 * 1024 * 1024))) ?: '';
                $tris = substr_count($ascii, 'facet normal');
                if (preg_match_all('/vertex\s+(-?[\d.eE+]+)\s+(-?[\d.eE+]+)\s+(-?[\d.eE+]+)/', $ascii, $m, PREG_SET_ORDER)) {
                    foreach ($m as $vtx) {
                        [$x, $y, $z] = [(float) $vtx[1], (float) $vtx[2], (float) $vtx[3]];
                        $minX = min($minX, $x);
                        $maxX = max($maxX, $x);
                        $minY = min($minY, $y);
                        $maxY = max($maxY, $y);
                        $minZ = min($minZ, $z);
                        $maxZ = max($maxZ, $z);
                    }
                }
            }
            $out = ['Format' => $binary ? 'Binary' : 'ASCII', 'Triangles' => (string) $tris];
            if (is_finite($minX) && is_finite($maxX)) {
                $out['Size (mm)'] = sprintf('%.1f × %.1f × %.1f', $maxX - $minX, $maxY - $minY, $maxZ - $minZ);
            }

            return $out;
        } finally {
            fclose($fh);
        }
    }

    /**
     * @return array<string, string>
     */
    private function textStats(string $path): array
    {
        $stream = Storage::disk($this->diskName())->readStream($path);
        if (! is_resource($stream)) {
            return [];
        }
        $lines = 0;
        $words = 0;
        $read = 0;
        try {
            while (! feof($stream) && $read < 8 * 1024 * 1024) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    break;
                }
                $read += strlen($chunk);
                $lines += substr_count($chunk, "\n");
                $words += str_word_count($chunk);
            }
        } finally {
            fclose($stream);
        }

        return ['Lines' => (string) $lines, 'Words' => (string) $words];
    }

    /**
     * @return array<string, string>
     */
    private function zip(string $localPath, string $kind): array
    {
        $zip = new ZipArchive;
        if ($zip->open($localPath) !== true) {
            return [];
        }
        try {
            $entries = $zip->numFiles;
            $total = 0;
            for ($i = 0; $i < $entries; $i++) {
                $stat = $zip->statIndex($i);
                if (is_array($stat)) {
                    $total += (int) ($stat['size'] ?? 0);
                }
            }
            $out = ['Entries' => (string) $entries, 'Uncompressed' => $this->humanBytes($total)];

            if ($kind === 'office') {
                // Core document properties live in docProps/core.xml (dc:creator/title).
                $core = $zip->getFromName('docProps/core.xml');
                if (is_string($core) && $core !== '') {
                    foreach (['dc:creator' => 'Author', 'dc:title' => 'Title'] as $tag => $label) {
                        if (preg_match('#<'.preg_quote($tag, '#').'>(.*?)</'.preg_quote($tag, '#').'>#s', $core, $mm) === 1) {
                            $v = $this->scalar(html_entity_decode(strip_tags($mm[1])));
                            if ($v !== null && $v !== '') {
                                $out[$label] = $v;
                            }
                        }
                    }
                }
                $app = $zip->getFromName('docProps/app.xml');
                if (is_string($app) && preg_match('#<Words>(\d+)</Words>#', $app, $wm) === 1) {
                    $out['Words'] = $wm[1];
                }
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    /** Narrow an unpack()/mixed value to a float (0.0 if non-numeric). */
    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /** Coerce an exiftool value to a short scalar string, or null if unusable. */
    private function scalar(mixed $v): ?string
    {
        if (is_bool($v)) {
            return $v ? 'yes' : 'no';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || ! mb_check_encoding($v, 'UTF-8')) {
            return null;
        }

        return mb_substr($v, 0, self::VALUE_CAP);
    }

    private function humanBytes(int $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $b;
        while ($n >= 1024 && $i < count($u) - 1) {
            $n /= 1024;
            $i++;
        }

        return sprintf($i === 0 ? '%d %s' : '%.1f %s', $n, $u[$i]);
    }

    /** Stream a disk file to a local temp file (RAII) so binaries can read it. */
    private function stage(string $path, string $ext, int $size): ?DiskTempFile
    {
        if ($size > self::MAX_BYTES) {
            return null;
        }
        $stream = Storage::disk($this->diskName())->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }
        $tmp = DiskTempFile::create('llfmeta')->withExtension($ext);
        $out = fopen($tmp->path(), 'wb');
        if ($out === false) {
            fclose($stream);

            return null;
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return $tmp;
    }

    private function diskName(): string
    {
        $d = config('files.disk');

        return is_string($d) && $d !== '' ? $d : 'local';
    }
}
