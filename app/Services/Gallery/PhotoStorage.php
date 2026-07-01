<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Stores photo originals and, on the queue, generates their renditions and reads
 * their EXIF metadata (capture date, GPS, camera). Originals live under a
 * date-structured object-storage prefix, independent of the files module.
 */
class PhotoStorage
{
    private const THUMB_WIDTH = 400;

    private const MEDIUM_WIDTH = 1600;

    /**
     * Persist just the original quickly (upload path). Renditions and EXIF are
     * filled later by process(). Returns the new photo's provisional data.
     *
     * @return array{uuid: string, disk_path: string, size: int, checksum: ?string}
     */
    public function storeOriginal(UploadedFile $upload): array
    {
        $uuid = (string) Str::uuid();
        $dir = 'photos/'.Carbon::now()->format('Y/m');
        $ext = strtolower($upload->getClientOriginalExtension() ?: 'jpg');
        $path = "{$dir}/{$uuid}.{$ext}";

        Storage::disk(config('files.disk'))->put($path, file_get_contents($upload->getRealPath()));

        return [
            'uuid' => $uuid,
            'disk_path' => $path,
            'size' => (int) $upload->getSize(),
            'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
        ];
    }

    /**
     * Generate the thumbnail and medium renditions and fill EXIF-derived
     * metadata on a stored photo. Runs on the queue.
     */
    public function process(Photo $photo): void
    {
        $disk = Storage::disk(config('files.disk'));

        // Work on a local copy of the original (the disk may be remote S3).
        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($tmp, $disk->get($photo->disk_path));

        try {
            $manager = new ImageManager(new Driver);

            $image = $this->transform($manager->decodePath($tmp), $photo);
            $width = $image->width();
            $height = $image->height();

            $dir = dirname($photo->disk_path);
            $thumbPath = "{$dir}/thumb/{$photo->uuid}.jpg";
            $mediumPath = "{$dir}/medium/{$photo->uuid}.jpg";

            $disk->put($thumbPath, (string) $image->scaleDown(width: self::THUMB_WIDTH)->encode(new JpegEncoder(quality: 75)));
            $disk->put($mediumPath, (string) $this->transform($manager->decodePath($tmp), $photo)->scaleDown(width: self::MEDIUM_WIDTH)->encode(new JpegEncoder(quality: 82)));

            $attributes = [
                'thumb_path' => $thumbPath,
                'medium_path' => $mediumPath,
                'width' => $width,
                'height' => $height,
                'status' => 'ready',
                'processed_at' => Carbon::now(),
            ];

            // Only pull date/location/camera from EXIF when the user has not
            // edited them by hand (meta_locked).
            if (! $photo->meta_locked) {
                $meta = $this->exif($tmp, $photo->mime_type);
                $attributes['taken_at'] = $meta['taken_at'] ?? $photo->taken_at;
                $attributes['latitude'] = $meta['lat'];
                $attributes['longitude'] = $meta['lon'];
                $attributes['camera'] = $meta['camera'];
            }

            $photo->forceFill($attributes)->save();
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Apply the photo's stored, non-destructive edits (clockwise rotation and a
     * horizontal flip) to a decoded image.
     */
    private function transform(ImageInterface $image, Photo $photo): ImageInterface
    {
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

    /**
     * @return array{taken_at: ?Carbon, lat: ?float, lon: ?float, camera: ?string}
     */
    private function exif(string $path, string $mime): array
    {
        $out = ['taken_at' => null, 'lat' => null, 'lon' => null, 'camera' => null];

        if (! function_exists('exif_read_data') || ! in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return $out;
        }

        $data = @exif_read_data($path, null, true);
        if ($data === false || $data === null) {
            return $out;
        }

        $raw = $data['EXIF']['DateTimeOriginal'] ?? ($data['IFD0']['DateTime'] ?? null);
        if (is_string($raw)) {
            $parsed = Carbon::createFromFormat('Y:m:d H:i:s', $raw);
            $out['taken_at'] = $parsed !== false ? $parsed : null;
        }

        $gps = $data['GPS'] ?? null;
        if (is_array($gps) && isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
            $out['lat'] = $this->gps($gps['GPSLatitude'], (string) $gps['GPSLatitudeRef']);
            $out['lon'] = $this->gps($gps['GPSLongitude'], (string) $gps['GPSLongitudeRef']);
        }

        $ifd0 = $data['IFD0'] ?? [];
        $camera = trim(($ifd0['Make'] ?? '').' '.($ifd0['Model'] ?? ''));
        $out['camera'] = $camera !== '' ? $camera : null;

        return $out;
    }

    /**
     * @param  mixed  $coordinate
     */
    private function gps($coordinate, string $ref): ?float
    {
        if (! is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $decimal = $this->frac($coordinate[0]) + $this->frac($coordinate[1]) / 60 + $this->frac($coordinate[2]) / 3600;

        if (in_array(strtoupper($ref), ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }

    private function frac(mixed $value): float
    {
        if (is_string($value) && str_contains($value, '/')) {
            [$n, $d] = array_pad(explode('/', $value, 2), 2, '1');

            return ((float) $d) != 0.0 ? (float) $n / (float) $d : 0.0;
        }

        return (float) $value;
    }
}
