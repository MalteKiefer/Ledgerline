<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Writes a photo's current metadata (capture date, GPS, camera) into a file with
 * exiftool. Used for the "edited" export of formats PEL cannot write — HEIC/HEIF
 * and AVIF — which exiftool handles natively, so an edited HEIC stays a HEIC and
 * still carries the user's metadata. Edits in place (-overwrite_original).
 */
class ExifWriter
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
     * Write date/GPS/camera into $path. When the pixels were already rotated the
     * orientation tag is reset to 1. Best-effort: returns false on any failure.
     */
    public function write(string $path, Photo $photo, bool $resetOrientation): bool
    {
        $args = $this->tagArgs($photo, $resetOrientation);
        if ($args === []) {
            return true; // nothing to write
        }

        try {
            $process = new Process(array_merge(
                [$this->binary(), '-overwrite_original', '-m', '-q'],
                $args,
                [$path],
            ));
            $process->setTimeout(120);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function tagArgs(Photo $photo, bool $resetOrientation): array
    {
        $args = [];

        if ($photo->taken_at !== null) {
            $when = $photo->taken_at->format('Y:m:d H:i:s');
            $args[] = "-DateTimeOriginal={$when}";
            $args[] = "-CreateDate={$when}";
            $args[] = "-ModifyDate={$when}";
        }

        if (filled($photo->camera)) {
            $args[] = '-Model='.$photo->camera;
        }

        if ($photo->latitude !== null && $photo->longitude !== null) {
            $lat = (float) $photo->latitude;
            $lon = (float) $photo->longitude;
            // -n: write the numeric value verbatim rather than parsing a string.
            $args[] = '-n';
            $args[] = '-GPSLatitude='.abs($lat);
            $args[] = '-GPSLatitudeRef='.($lat >= 0 ? 'N' : 'S');
            $args[] = '-GPSLongitude='.abs($lon);
            $args[] = '-GPSLongitudeRef='.($lon >= 0 ? 'E' : 'W');
        }

        if ($resetOrientation) {
            $args[] = '-Orientation#=1'; // '#' forces the numeric form
        }

        return $args;
    }

    private function binary(): string
    {
        return (string) config('gallery.exiftool_path', 'exiftool');
    }
}
