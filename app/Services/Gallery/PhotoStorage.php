<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\CompanyProfile;
use App\Models\Photo;
use App\Services\Files\ReverseGeocoder;
use Illuminate\Contracts\Filesystem\Filesystem;
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
        private readonly VideoProcessor $video,
        private readonly MotionPhotoExtractor $motion,
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
     * pass (upload path). The original is downloaded once and reused so a large
     * video is not fetched (and held in memory) several times.
     */
    public function process(Photo $photo): void
    {
        $disk = Storage::disk(config('files.disk'));
        $tmp = $this->download($photo, $disk);

        try {
            $this->generateRenditions($photo, $tmp, $disk);
            $this->readMetadataFrom($photo, $tmp, $disk);
            $this->applyNameTemplate($photo);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Stream the original to a local temp file without loading it entirely into
     * memory (large videos would otherwise blow the worker's memory limit).
     */
    private function download(Photo $photo, Filesystem $disk): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        $source = $disk->readStream($photo->disk_path);

        try {
            // Passing the stream resource streams it to disk without holding the
            // whole (possibly large) file in memory.
            file_put_contents($tmp, $source);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        return $tmp;
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
     * Generate the thumbnail and medium renditions from the untouched original.
     * Images apply the stored rotation/flip; videos use an extracted poster
     * frame. Marks the photo ready.
     */
    public function renditions(Photo $photo): void
    {
        $disk = Storage::disk(config('files.disk'));
        $tmp = $this->download($photo, $disk);

        try {
            $this->generateRenditions($photo, $tmp, $disk);
        } finally {
            @unlink($tmp);
        }
    }

    private function generateRenditions(Photo $photo, string $tmp, Filesystem $disk): void
    {
        $poster = null;

        try {
            $source = $photo->isVideo() ? ($poster = $this->posterFrame($photo, $tmp)) : $tmp;

            $manager = new ImageManager(new Driver);
            $image = $this->transform($manager->decodePath($source), $photo);
            $width = $image->width();
            $height = $image->height();

            $dir = dirname($photo->disk_path);
            $thumbPath = "{$dir}/thumb/{$photo->uuid}.jpg";
            $mediumPath = "{$dir}/medium/{$photo->uuid}.jpg";

            $disk->put($thumbPath, (string) $image->scaleDown(width: self::THUMB_WIDTH)->encode(new JpegEncoder(quality: 75)));
            $disk->put($mediumPath, (string) $this->transform($manager->decodePath($source), $photo)->scaleDown(width: self::MEDIUM_WIDTH)->encode(new JpegEncoder(quality: 82)));

            $attributes = [
                'thumb_path' => $thumbPath,
                'medium_path' => $mediumPath,
                'status' => 'ready',
                'processed_at' => Carbon::now(),
            ];

            // For videos the native pixel size comes from ffprobe, not the
            // (possibly downscaled) poster frame.
            if (! $photo->isVideo()) {
                $attributes['width'] = $width;
                $attributes['height'] = $height;
            }

            $photo->forceFill($attributes)->save();
        } finally {
            if ($poster !== null) {
                @unlink($poster);
            }
        }
    }

    /**
     * Extract a poster frame from a local video into a temporary JPEG and return
     * its path.
     */
    private function posterFrame(Photo $photo, string $videoTmp): string
    {
        $second = (int) (CompanyProfile::current()->gallery_video_frame ?? 1);
        $poster = tempnam(sys_get_temp_dir(), 'poster').'.jpg';
        $this->video->poster($videoTmp, $second, $poster);

        return $poster;
    }

    /**
     * Read the photo's EXIF metadata: always store the full dump, and (unless
     * the user has hand-edited them) the capture date, GPS, camera and the
     * reverse-geocoded place name.
     */
    public function readMetadata(Photo $photo): void
    {
        $disk = Storage::disk(config('files.disk'));
        $tmp = $this->download($photo, $disk);

        try {
            $this->readMetadataFrom($photo, $tmp, $disk);
        } finally {
            @unlink($tmp);
        }
    }

    private function readMetadataFrom(Photo $photo, string $tmp, Filesystem $disk): void
    {
        if ($photo->isVideo()) {
            $this->readVideoMetadata($photo, $tmp);

            return;
        }

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

        $attributes = $this->applyPlace($attributes, $attributes['latitude'] ?? $photo->latitude, $attributes['longitude'] ?? $photo->longitude);
        $attributes['motion_path'] = $this->extractMotion($photo, $tmp, $disk);

        $photo->forceFill($attributes)->save();
    }

    /**
     * Reverse-geocode the coordinates and set both the display name and the
     * structured address parts.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyPlace(array $attributes, mixed $lat, mixed $lon): array
    {
        if ($lat === null || $lon === null) {
            $attributes['place'] = null;
            $attributes['place_details'] = null;

            return $attributes;
        }

        $geo = $this->geocoder->lookupDetailed((float) $lat, (float) $lon);
        $attributes['place'] = $geo['display'];
        $attributes['place_details'] = $geo['address'] ?: null;

        return $attributes;
    }

    /**
     * Extract a motion photo's embedded clip to its own object and return the
     * path, or null when the image carries no motion clip.
     */
    private function extractMotion(Photo $photo, string $localPath, Filesystem $disk): ?string
    {
        $clip = $this->motion->extract($localPath);
        if ($clip === null) {
            return null;
        }

        $path = dirname($photo->disk_path)."/motion/{$photo->uuid}.mp4";
        $disk->put($path, $clip);

        return $path;
    }

    /**
     * Read a video's metadata via ffprobe: native dimensions, duration, a full
     * dump, and the capture date from the container's creation time (unless the
     * user has locked it).
     */
    private function readVideoMetadata(Photo $photo, string $localPath): void
    {
        $probe = $this->video->probe($localPath);

        $attributes = [
            'metadata' => $probe['raw'],
            'duration' => $probe['duration'],
        ];

        if ($probe['width'] !== null && $probe['height'] !== null) {
            $attributes['width'] = $probe['width'];
            $attributes['height'] = $probe['height'];
        }

        $tags = $probe['raw']['format']['tags'] ?? [];

        if (! $photo->meta_locked) {
            // Prefer the local-timezone creation date over the UTC creation_time.
            $created = $tags['com.apple.quicktime.creationdate'] ?? ($tags['creation_time'] ?? null);
            if (is_string($created)) {
                try {
                    $attributes['taken_at'] = Carbon::parse($created);
                } catch (\Throwable) {
                    // Leave the existing capture date in place.
                }
            }

            [$lat, $lon] = $this->videoLocation($tags);
            $attributes['latitude'] = $lat;
            $attributes['longitude'] = $lon;
            $attributes['camera'] = $this->videoCamera($tags);
        }

        // Reverse-geocode whatever coordinates the video now has so it shows a
        // place and joins the map and trips like a photo.
        $attributes = $this->applyPlace($attributes, $attributes['latitude'] ?? $photo->latitude, $attributes['longitude'] ?? $photo->longitude);

        $photo->forceFill($attributes)->save();
    }

    /**
     * Extract latitude/longitude from a video's container tags. Phones store an
     * ISO 6709 string such as "+37.7858-122.4064+010.000/".
     *
     * @param  array<string, mixed>  $tags
     * @return array{0: ?float, 1: ?float}
     */
    private function videoLocation(array $tags): array
    {
        $iso = $tags['com.apple.quicktime.location.ISO6709']
            ?? ($tags['location-eng'] ?? ($tags['location'] ?? null));

        if (is_string($iso) && preg_match('/([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)/', $iso, $m)) {
            return [round((float) $m[1], 7), round((float) $m[2], 7)];
        }

        return [null, null];
    }

    /**
     * Build a camera label from a video's make/model tags, if present.
     *
     * @param  array<string, mixed>  $tags
     */
    private function videoCamera(array $tags): ?string
    {
        $make = $tags['com.apple.quicktime.make'] ?? ($tags['make'] ?? '');
        $model = $tags['com.apple.quicktime.model'] ?? ($tags['model'] ?? '');
        $camera = trim(trim((string) $make).' '.trim((string) $model));

        return $camera !== '' ? $camera : null;
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
