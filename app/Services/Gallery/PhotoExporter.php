<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\HeicEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
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
 * a PNG eXIf chunk, exiftool for HEIC/HEIF/AVIF (which keep their format), and
 * container tags for videos (via ffmpeg, stream-copied). WebP/GIF keep their
 * format with the transforms only.
 *
 * Returns a temp file path so large videos never sit whole in memory; the
 * caller streams it and deletes it.
 */
class PhotoExporter
{
    public function __construct(
        private readonly VideoProcessor $video,
        private readonly ExifWriter $exifWriter,
    ) {}

    private ?ImageManager $manager = null;

    private function imageManager(): ImageManager
    {
        return $this->manager ??= new ImageManager($this->driver());
    }

    private function driver(): DriverInterface
    {
        return extension_loaded('imagick') ? new ImagickDriver : new GdDriver;
    }

    /**
     * Build the edited export(s) for a photo and return them as
     * [{name, path}, …]. A plain photo or video yields one file; a motion photo
     * yields two — the still (with EXIF) and its motion clip (with container
     * metadata) — so nothing about the item is lost on export. The caller must
     * delete every returned path.
     *
     * @return list<array{name: string, path: string}>
     */
    public function editedFiles(Photo $photo): array
    {
        $baseName = $photo->name ?: ('photo-'.$photo->id);
        $files = [];

        if ($photo->media_type === 'video' || str_starts_with((string) $photo->mime_type, 'video/')) {
            $files[] = ['name' => $baseName, 'path' => $this->editVideo($this->localCopy($photo->disk_path), $photo)];

            return $files;
        }

        // The still image (with baked-in edits + EXIF).
        $files[] = ['name' => $baseName, 'path' => $this->editImage($this->localCopy($photo->disk_path), $photo)];

        // A motion photo also carries a short clip: export it too, with the same
        // metadata written into its container.
        if ($photo->hasMotion()) {
            $clip = $this->editVideo($this->localCopy($photo->motion_path), $photo);
            $files[] = ['name' => $this->stripExtension($baseName).' (motion).mp4', 'path' => $clip];
        }

        return $files;
    }

    /**
     * Whether a single edited download must be packaged as a zip (more than one
     * output file, i.e. a motion photo).
     */
    public function isBundle(Photo $photo): bool
    {
        return $photo->hasMotion() && $photo->media_type !== 'video';
    }

    /**
     * Stream one of the photo's object-storage files to a local temp path so it
     * can be transcoded/rewritten without holding it in memory.
     */
    private function localCopy(string $path): string
    {
        $disk = Storage::disk(config('files.disk'));
        $tmp = tempnam(sys_get_temp_dir(), 'export-src');
        $stream = $disk->readStream($path);
        try {
            file_put_contents($tmp, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $tmp;
    }

    private function stripExtension(string $name): string
    {
        $dot = strrpos($name, '.');

        return $dot > 0 ? substr($name, 0, $dot) : $name;
    }

    private function editImage(string $src, Photo $photo): string
    {
        $mime = (string) $photo->mime_type;

        // HEIC/HEIF/AVIF: keep the format (re-encode via Imagick when rotated) and
        // write metadata with exiftool, which PEL cannot do. Handled separately so
        // an edited HEIC stays a valid HEIC with the user's metadata.
        if (in_array($mime, ['image/heic', 'image/heif', 'image/avif'], true)) {
            return $this->editHeif($src, $photo, $mime);
        }

        $bytes = (string) file_get_contents($src);

        $transformed = (((int) $photo->rotation) % 360 !== 0) || $photo->flipped;
        $isJpeg = in_array($mime, ['image/jpeg', 'image/pjpeg'], true);
        $isPng = $mime === 'image/png';

        if ($transformed) {
            $bytes = (string) $this->transform($src, $photo)->encode(match ($mime) {
                'image/png' => new PngEncoder,
                'image/webp' => new WebpEncoder(quality: 90),
                default => new JpegEncoder(quality: 92),
            });
        }

        // Write the current metadata into the pixels' container. JPEG carries a
        // full EXIF/APP1 segment; PNG gets an eXIf chunk (both built from PEL).
        // WebP/GIF cannot reliably hold EXIF here, so they keep transforms only.
        try {
            if ($isJpeg) {
                $bytes = $this->writeJpegExif($bytes, $photo, $transformed);
            } elseif ($isPng) {
                $bytes = $this->writePngExif($bytes, $photo, $transformed);
            }
        } catch (Throwable) {
            // Metadata is best-effort; fall back to the (transformed) pixels.
        }

        @unlink($src);
        $dest = tempnam(sys_get_temp_dir(), 'export').'.'.$this->extension($photo, 'jpg');
        file_put_contents($dest, $bytes);

        return $dest;
    }

    /**
     * Edited export for HEIC/HEIF/AVIF: bake rotation/flip by re-encoding to the
     * same format with Imagick (when transformed), then write the current metadata
     * in place with exiftool. Keeps the original format and extension so the file
     * is not mislabelled.
     */
    private function editHeif(string $src, Photo $photo, string $mime): string
    {
        $transformed = (((int) $photo->rotation) % 360 !== 0) || $photo->flipped;
        $fallbackExt = $mime === 'image/avif' ? 'avif' : 'heic';
        $dest = tempnam(sys_get_temp_dir(), 'export').'.'.$this->extension($photo, $fallbackExt);

        $encoded = false;
        if ($transformed && extension_loaded('imagick')) {
            try {
                $encoder = $mime === 'image/avif' ? new AvifEncoder(quality: 90) : new HeicEncoder(quality: 90);
                file_put_contents($dest, (string) $this->transform($src, $photo)->encode($encoder));
                $encoded = true;
            } catch (Throwable) {
                // No HEIC/AVIF encode delegate (libheif built without an encoder):
                // keep the original bytes rather than failing the whole export.
                $encoded = false;
            }
        }

        if (! $encoded) {
            // No transform, no Imagick, or encoding unavailable: keep the original
            // bytes (still a valid file that gets the metadata written below).
            copy($src, $dest);
        }

        @unlink($src);

        // Best-effort metadata write; the file stays valid even if exiftool fails.
        $this->exifWriter->write($dest, $photo, $transformed);

        return $dest;
    }

    /**
     * Decode $src and apply the stored rotation (clockwise) and flip.
     */
    private function transform(string $src, Photo $photo): ImageInterface
    {
        $image = $this->imageManager()->decodePath($src);

        $rotation = ((int) $photo->rotation) % 360;
        if ($rotation !== 0) {
            // Intervention rotates counter-clockwise; negate for clockwise.
            $image->rotate(360 - $rotation);
        }
        if ($photo->flipped) {
            $image->flop();
        }

        return $image;
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
     * Write the photo's current metadata into a JPEG's EXIF segment.
     */
    private function writeJpegExif(string $bytes, Photo $photo, bool $resetOrientation): string
    {
        $jpeg = new PelJpeg($bytes);

        $exif = $jpeg->getExif();
        if ($exif === null) {
            $exif = new PelExif;
            $jpeg->setExif($exif);
            $exif->setTiff(new PelTiff);
        }

        $tiff = $exif->getTiff();
        $this->fillTiff($tiff, $photo, $resetOrientation);

        return $jpeg->getBytes();
    }

    /**
     * Write the metadata into a PNG's eXIf chunk (PNG 1.5+), inserted right
     * after IHDR. The chunk payload is the raw TIFF block PEL produces.
     */
    private function writePngExif(string $bytes, Photo $photo, bool $resetOrientation): string
    {
        // Not a PNG (signature mismatch): leave it untouched.
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return $bytes;
        }

        $tiff = new PelTiff;
        $this->fillTiff($tiff, $photo, $resetOrientation);
        $data = $tiff->getBytes();

        $chunk = pack('N', strlen($data)).'eXIf'.$data;
        $chunk .= pack('N', crc32('eXIf'.$data));

        // IHDR is always the first chunk: 8-byte signature + (4 len + 4 type +
        // 13 data + 4 crc) = 33 bytes. Insert the eXIf chunk immediately after.
        return substr($bytes, 0, 33).$chunk.substr($bytes, 33);
    }

    /**
     * Populate a PEL TIFF block with capture date, camera and GPS, resetting the
     * orientation to 1 when the pixels were already rotated.
     */
    private function fillTiff(PelTiff $tiff, Photo $photo, bool $resetOrientation): void
    {
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
