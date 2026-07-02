<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryShort;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;
use Throwable;

/**
 * Produces the "edited" export of a photo or video: the stored non-destructive
 * edits (rotation / flip) baked into image pixels, and the user's current
 * metadata (capture date, GPS, camera) written into the file — EXIF for JPEG,
 * container tags for videos (via ffmpeg, stream-copied). Other image formats
 * keep their format and get the transforms only.
 *
 * Returns a temp file path so large videos never sit whole in memory; the
 * caller streams it and deletes it.
 */
class PhotoExporter
{
    public function __construct(private readonly VideoProcessor $video) {}

    /**
     * Build the edited export and return its temp file path (caller must delete).
     */
    public function editedFile(Photo $photo): string
    {
        $disk = Storage::disk(config('files.disk'));
        $src = tempnam(sys_get_temp_dir(), 'export-src');
        $stream = $disk->readStream($photo->disk_path);
        try {
            file_put_contents($src, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($photo->media_type === 'video' || str_starts_with((string) $photo->mime_type, 'video/')) {
            return $this->editVideo($src, $photo);
        }

        return $this->editImage($src, $photo);
    }

    private function editImage(string $src, Photo $photo): string
    {
        $bytes = (string) file_get_contents($src);

        $transformed = (((int) $photo->rotation) % 360 !== 0) || $photo->flipped;
        $isJpeg = in_array($photo->mime_type, ['image/jpeg', 'image/pjpeg'], true);

        if ($transformed) {
            $image = (new ImageManager(new Driver))->decodePath($src);

            $rotation = ((int) $photo->rotation) % 360;
            if ($rotation !== 0) {
                // Intervention rotates counter-clockwise; negate for clockwise.
                $image->rotate(360 - $rotation);
            }
            if ($photo->flipped) {
                $image->flop();
            }

            $encoder = match ($photo->mime_type) {
                'image/png' => new PngEncoder,
                'image/webp' => new WebpEncoder(quality: 90),
                default => new JpegEncoder(quality: 92),
            };
            $bytes = (string) $image->encode($encoder);
        }

        if ($isJpeg) {
            try {
                $bytes = $this->writeExif($bytes, $photo, $transformed);
            } catch (Throwable) {
                // EXIF is best-effort; fall back to the (transformed) pixels.
            }
        }

        @unlink($src);
        $dest = tempnam(sys_get_temp_dir(), 'export').'.'.$this->extension($photo, 'jpg');
        file_put_contents($dest, $bytes);

        return $dest;
    }

    private function editVideo(string $src, Photo $photo): string
    {
        $dest = tempnam(sys_get_temp_dir(), 'export').'.'.$this->extension($photo, 'mp4');

        $metadata = [];
        if ($photo->taken_at !== null) {
            $metadata['creation_time'] = $photo->taken_at->toIso8601String();
        }
        if (filled($photo->camera)) {
            $metadata['model'] = (string) $photo->camera;
        }
        if ($photo->latitude !== null && $photo->longitude !== null) {
            // ISO 6709, as used by Apple/Android for the QuickTime location tag.
            $metadata['location'] = sprintf('%+.4f%+.4f/', (float) $photo->latitude, (float) $photo->longitude);
        }

        try {
            $this->video->writeMetadata($src, $dest, $metadata);
            @unlink($src);

            return $dest;
        } catch (Throwable) {
            // ffmpeg unavailable or failed: fall back to the untouched original.
            @unlink($dest);

            return $src;
        }
    }

    private function extension(Photo $photo, string $fallback): string
    {
        $ext = pathinfo((string) $photo->name, PATHINFO_EXTENSION);

        return $ext !== '' ? strtolower($ext) : $fallback;
    }

    /**
     * Patch capture date, camera and GPS into a JPEG's EXIF, resetting the
     * orientation to 1 when the pixels were already rotated.
     */
    private function writeExif(string $bytes, Photo $photo, bool $resetOrientation): string
    {
        $jpeg = new PelJpeg($bytes);

        $exif = $jpeg->getExif();
        if ($exif === null) {
            $exif = new PelExif;
            $jpeg->setExif($exif);
            $exif->setTiff(new PelTiff);
        }

        $tiff = $exif->getTiff();
        $ifd0 = $tiff->getIfd();
        if ($ifd0 === null) {
            $ifd0 = new PelIfd(PelIfd::IFD0);
            $tiff->setIfd($ifd0);
        }

        if (filled($photo->camera)) {
            $ifd0->addEntry(new PelEntryAscii(PelTag::MODEL, (string) $photo->camera));
        }

        if ($resetOrientation) {
            $ifd0->addEntry(new PelEntryShort(PelTag::ORIENTATION, 1));
        }

        $exifIfd = $ifd0->getSubIfd(PelIfd::EXIF);
        if ($exifIfd === null) {
            $exifIfd = new PelIfd(PelIfd::EXIF);
            $ifd0->addSubIfd($exifIfd);
        }

        if ($photo->taken_at !== null) {
            $when = $photo->taken_at->format('Y:m:d H:i:s');
            $exifIfd->addEntry(new PelEntryAscii(PelTag::DATE_TIME_ORIGINAL, $when));
            $ifd0->addEntry(new PelEntryAscii(PelTag::DATE_TIME, $when));
        }

        if ($photo->latitude !== null && $photo->longitude !== null) {
            $gps = $ifd0->getSubIfd(PelIfd::GPS);
            if ($gps === null) {
                $gps = new PelIfd(PelIfd::GPS);
                $ifd0->addSubIfd($gps);
            }
            $lat = (float) $photo->latitude;
            $lon = (float) $photo->longitude;
            $gps->addEntry(new PelEntryAscii(PelTag::GPS_LATITUDE_REF, $lat >= 0 ? 'N' : 'S'));
            $gps->addEntry($this->degrees(PelTag::GPS_LATITUDE, abs($lat)));
            $gps->addEntry(new PelEntryAscii(PelTag::GPS_LONGITUDE_REF, $lon >= 0 ? 'E' : 'W'));
            $gps->addEntry($this->degrees(PelTag::GPS_LONGITUDE, abs($lon)));
        }

        return $jpeg->getBytes();
    }

    /**
     * A GPS coordinate as degrees/minutes/seconds rationals.
     */
    private function degrees(int $tag, float $value): PelEntryRational
    {
        $deg = (int) floor($value);
        $minutesFloat = ($value - $deg) * 60;
        $min = (int) floor($minutesFloat);
        $sec = (int) round(($minutesFloat - $min) * 60 * 100);

        return new PelEntryRational($tag, [$deg, 1], [$min, 1], [$sec, 100]);
    }
}
