<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\File;
use Illuminate\Support\Facades\Storage;

/**
 * Reads EXIF metadata from an image file: a curated set of human-readable fields
 * and, when present, GPS coordinates as decimal degrees.
 */
class ImageExif
{
    /**
     * @return array{fields: array<string, string>, gps: ?array{0: float, 1: float}}|null
     */
    public function read(File $file): ?array
    {
        if (! $file->isImage() || ! function_exists('exif_read_data')) {
            return null;
        }

        $disk = Storage::disk(config('files.disk'));

        if (! $disk->exists($file->disk_path)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'exif');
        if ($tmp === false) {
            return null;
        }

        file_put_contents($tmp, $disk->get($file->disk_path));
        $data = @exif_read_data($tmp, null, true);
        @unlink($tmp);

        if ($data === false || $data === null) {
            return null;
        }

        return [
            'fields' => $this->fields($data),
            'gps' => $this->gpsFromExifArray($data),
        ];
    }

    /**
     * A curated, human-readable subset of the EXIF data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function fields(array $data): array
    {
        $ifd0 = $data['IFD0'] ?? [];
        $exif = $data['EXIF'] ?? [];

        $map = [
            'Camera' => trim(($ifd0['Make'] ?? '').' '.($ifd0['Model'] ?? '')),
            'Taken' => $exif['DateTimeOriginal'] ?? ($ifd0['DateTime'] ?? ''),
            'Software' => $ifd0['Software'] ?? '',
            'Exposure' => isset($exif['ExposureTime']) ? $exif['ExposureTime'].' s' : '',
            'Aperture' => isset($exif['FNumber']) ? 'f/'.$this->rational($exif['FNumber']) : '',
            'ISO' => isset($exif['ISOSpeedRatings']) ? (string) $exif['ISOSpeedRatings'] : '',
            'Focal length' => isset($exif['FocalLength']) ? $this->rational($exif['FocalLength']).' mm' : '',
            'Dimensions' => isset($exif['ExifImageWidth'], $exif['ExifImageLength'])
                ? $exif['ExifImageWidth'].' × '.$exif['ExifImageLength']
                : '',
        ];

        return array_filter($map, fn (string $v): bool => trim($v) !== '');
    }

    /**
     * Extract decimal GPS coordinates from a sectioned EXIF array.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: float}|null
     */
    public function gpsFromExifArray(array $data): ?array
    {
        $gps = $data['GPS'] ?? null;

        if (! is_array($gps) || ! isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
            return null;
        }

        $lat = $this->toDecimal($gps['GPSLatitude'], (string) $gps['GPSLatitudeRef']);
        $lon = $this->toDecimal($gps['GPSLongitude'], (string) $gps['GPSLongitudeRef']);

        if ($lat === null || $lon === null) {
            return null;
        }

        return [$lat, $lon];
    }

    /**
     * Convert an EXIF degrees/minutes/seconds coordinate to decimal degrees.
     *
     * @param  mixed  $coordinate  A [deg, min, sec] array of rationals.
     */
    private function toDecimal(mixed $coordinate, string $ref): ?float
    {
        if (! is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $degrees = $this->rational($coordinate[0]);
        $minutes = $this->rational($coordinate[1]);
        $seconds = $this->rational($coordinate[2]);

        $decimal = $degrees + $minutes / 60 + $seconds / 3600;

        if (in_array(mb_strtoupper($ref), ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 6);
    }

    /**
     * Evaluate an EXIF rational like "1234/100".
     */
    private function rational(mixed $value): float
    {
        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = array_pad(explode('/', $value, 2), 2, '1');

            return ((float) $den) != 0.0 ? (float) $num / (float) $den : 0.0;
        }

        return (float) $value;
    }
}
