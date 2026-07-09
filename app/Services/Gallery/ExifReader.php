<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads still-image metadata with exiftool. PHP's exif_read_data() only handles
 * JPEG/TIFF, so HEIC/HEIF/AVIF (and Apple's rich tags) go through exiftool, which
 * is installed in the production image (EXIFTOOL_PATH). The parsing step is split
 * from the process call so it can be unit-tested against a captured JSON dump.
 */
class ExifReader
{
    public function available(): bool
    {
        try {
            $process = new Process([$this->binary(), '-ver']);
            $process->setTimeout(20);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Read one file's metadata into a normalised shape for the gallery processor.
     *
     * @return array{taken_at: ?Carbon, lat: ?float, lon: ?float, camera: ?string, content_id: ?string, raw: ?array<string, mixed>}
     */
    public function read(string $path): array
    {
        $empty = ['taken_at' => null, 'lat' => null, 'lon' => null, 'camera' => null, 'content_id' => null, 'raw' => null];

        try {
            // -n: numeric values (signed decimal GPS), -G: group-prefixed keys,
            // -json: machine-readable, -api largefilesupport for big HEIC bursts.
            $process = new Process([$this->binary(), '-json', '-n', '-G', '-api', 'largefilesupport=1', $path]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                return $empty;
            }

            $decoded = json_decode($process->getOutput(), true);
            $tags = is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : [];

            return $this->normalize($tags);
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * Read only the Apple Live Photo ContentIdentifier — cheap enough to run on
     * the JPEG fast path (which otherwise skips exiftool) so JPEG Live Photos
     * pair too. Returns null when absent or exiftool is unavailable.
     */
    public function readContentId(string $path): ?string
    {
        try {
            $process = new Process([$this->binary(), '-s3', '-ContentIdentifier', '-MakerNotes:ContentIdentifier', '-MediaGroupUUID', $path]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            foreach (preg_split('/\r?\n/', trim($process->getOutput())) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && $line !== '-') {
                    return $line;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Turn exiftool's group-prefixed tag map into normalised fields. Public so it
     * can be unit-tested with a fixture, no binary required.
     *
     * @param  array<string, mixed>  $tags
     * @return array{taken_at: ?Carbon, lat: ?float, lon: ?float, camera: ?string, content_id: ?string, raw: ?array<string, mixed>}
     */
    public function normalize(array $tags): array
    {
        return [
            'taken_at' => $this->takenAt($tags),
            'lat' => $this->number($this->first($tags, ['GPSLatitude', 'Composite:GPSLatitude', 'EXIF:GPSLatitude'])),
            'lon' => $this->number($this->first($tags, ['GPSLongitude', 'Composite:GPSLongitude', 'EXIF:GPSLongitude'])),
            'camera' => $this->camera($tags),
            'content_id' => $this->contentId($tags),
            'raw' => $tags === [] ? null : $this->clean($tags),
        ];
    }

    private function takenAt(array $tags): ?Carbon
    {
        $value = $this->first($tags, [
            'SubSecDateTimeOriginal', 'DateTimeOriginal', 'EXIF:DateTimeOriginal',
            'CreateDate', 'EXIF:CreateDate', 'QuickTime:CreateDate',
            'MediaCreateDate', 'Composite:DateTimeOriginal',
        ]);

        if (! is_string($value) || $value === '') {
            return null;
        }

        // exiftool dates look like "2024:06:30 14:22:05" or with a "+02:00" zone
        // and/or sub-second ".123"; keep only the leading date-time part.
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $value, $m)) {
            $parsed = Carbon::createFromFormat('Y:m:d H:i:s', "{$m[1]}:{$m[2]}:{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}");

            return $parsed !== false ? $parsed : null;
        }

        return null;
    }

    private function camera(array $tags): ?string
    {
        $make = trim((string) ($this->first($tags, ['Make', 'EXIF:Make']) ?? ''));
        $model = trim((string) ($this->first($tags, ['Model', 'EXIF:Model']) ?? ''));
        $camera = trim($make.' '.$model);

        return $camera !== '' ? $camera : null;
    }

    /**
     * Apple's Live Photo pairing key: a MakerNote tag on the still and a
     * QuickTime tag on the movie. Used by the pairing job.
     */
    private function contentId(array $tags): ?string
    {
        $value = $this->first($tags, [
            'ContentIdentifier', 'MakerNotes:ContentIdentifier', 'Apple:ContentIdentifier',
            'QuickTime:ContentIdentifier', 'MediaGroupUUID',
        ]);

        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  array<int, string>  $keys
     */
    private function first(array $tags, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $tags) && $tags[$key] !== '' && $tags[$key] !== null) {
                return $tags[$key];
            }
        }

        // Fall back to a case-insensitive suffix match (group prefixes vary by
        // exiftool version, e.g. "MakerNotes:ContentIdentifier").
        foreach ($keys as $key) {
            foreach ($tags as $tag => $value) {
                if ($value !== '' && $value !== null && str_ends_with(strtolower((string) $tag), ':'.strtolower($key))) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 7) : null;
    }

    /**
     * Keep only JSON-safe, UTF-8 values (drop binary blobs like embedded images).
     *
     * @param  array<string, mixed>  $tags
     * @return array<string, mixed>
     */
    private function clean(array $tags): array
    {
        $result = [];
        foreach ($tags as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->clean($value);
            } elseif (is_string($value)) {
                if (mb_check_encoding($value, 'UTF-8') && ! str_starts_with($value, 'base64:')) {
                    $result[$key] = $value;
                }
            } elseif (is_scalar($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function binary(): string
    {
        return (string) config('gallery.exiftool_path', 'exiftool');
    }
}
