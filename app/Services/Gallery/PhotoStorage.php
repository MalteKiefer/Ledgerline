<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\CompanyProfile;
use App\Models\Photo;
use App\Services\Files\ReverseGeocoder;
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
 * their EXIF metadata (capture date, GPS, camera, plus a full dump). Originals
 * live under a date-structured object-storage prefix, independent of the files
 * module. Rendition generation and metadata reading are separate steps so they
 * can be re-run independently.
 */
class PhotoStorage
{
    private const THUMB_WIDTH = 400;

    private const MEDIUM_WIDTH = 1600;

    public function __construct(
        private readonly ReverseGeocoder $geocoder,
        private readonly FilenameTemplate $filenameTemplate,
    ) {}

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
     * Generate renditions, read metadata and apply the filename template in one
     * pass (upload path).
     */
    public function process(Photo $photo): void
    {
        $this->renditions($photo);
        $this->readMetadata($photo);
        $this->applyNameTemplate($photo);
    }

    /**
     * Rename the photo's display name from the configured filename template. The
     * stored bytes and disk paths are untouched; only the name column changes.
     */
    public function applyNameTemplate(Photo $photo): void
    {
        $template = CompanyProfile::current()->gallery_filename_template;
        $name = $this->filenameTemplate->render($photo, $template);

        if ($name !== null && $name !== $photo->name) {
            $photo->forceFill(['name' => $name])->save();
        }
    }

    /**
     * Generate the thumbnail and medium renditions from the untouched original,
     * applying the photo's stored rotation/flip. Marks the photo ready.
     */
    public function renditions(Photo $photo): void
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

            $photo->forceFill([
                'thumb_path' => $thumbPath,
                'medium_path' => $mediumPath,
                'width' => $width,
                'height' => $height,
                'status' => 'ready',
                'processed_at' => Carbon::now(),
            ])->save();
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Read the photo's EXIF metadata: always store the full dump, and (unless
     * the user has hand-edited them) the capture date, GPS, camera and the
     * reverse-geocoded place name.
     */
    public function readMetadata(Photo $photo): void
    {
        $disk = Storage::disk(config('files.disk'));

        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($tmp, $disk->get($photo->disk_path));

        try {
            $meta = $this->exif($tmp, $photo->mime_type);
            $attributes = ['metadata' => $meta['raw']];

            // Only pull date/location/camera from EXIF when the user has not
            // edited them by hand (meta_locked). The place name always tracks
            // whatever coordinates the photo currently has.
            if (! $photo->meta_locked) {
                $attributes['taken_at'] = $meta['taken_at'] ?? $photo->taken_at;
                $attributes['latitude'] = $meta['lat'];
                $attributes['longitude'] = $meta['lon'];
                $attributes['camera'] = $meta['camera'];
            }

            $lat = $attributes['latitude'] ?? $photo->latitude;
            $lon = $attributes['longitude'] ?? $photo->longitude;
            $attributes['place'] = ($lat !== null && $lon !== null)
                ? $this->geocoder->lookup((float) $lat, (float) $lon)
                : null;

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
     * @return array{taken_at: ?Carbon, lat: ?float, lon: ?float, camera: ?string, raw: ?array<string, mixed>}
     */
    private function exif(string $path, string $mime): array
    {
        $out = ['taken_at' => null, 'lat' => null, 'lon' => null, 'camera' => null, 'raw' => null];

        if (! function_exists('exif_read_data') || ! in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return $out;
        }

        $data = @exif_read_data($path, null, true);
        if ($data === false || $data === null) {
            return $out;
        }

        $out['raw'] = $this->cleanMeta($data);

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
     * Make an exif_read_data() result safe to store as JSON: drop the raw
     * thumbnail bytes and any binary (non-UTF-8) values that would corrupt the
     * column, keeping the human-readable tags.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanMeta(array $data): array
    {
        unset($data['THUMBNAIL']);

        $clean = static function (array $input, callable $self): array {
            $result = [];
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = $self($value, $self);
                } elseif (is_string($value)) {
                    if (mb_check_encoding($value, 'UTF-8')) {
                        $result[$key] = $value;
                    }
                } elseif (is_scalar($value)) {
                    $result[$key] = $value;
                }
            }

            return $result;
        };

        return $clean($data, $clean);
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
