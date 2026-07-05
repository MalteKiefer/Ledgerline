<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SharesWithUsers;
use App\Observers\PhotoObserver;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A gallery photo. Bytes live under a date-structured object-storage prefix
 * (original + thumbnail + medium rendition); this row holds only metadata.
 * Sorted by capture date (EXIF DateTimeOriginal, or the upload time).
 */
#[Fillable([
    'uuid',
    'name',
    'original_name',
    'status',
    'media_type',
    'duration',
    'disk_path',
    'thumb_path',
    'medium_path',
    'motion_path',
    'mime_type',
    'size',
    'width',
    'height',
    'latitude',
    'longitude',
    'place',
    'place_details',
    'camera',
    'metadata',
    'rotation',
    'flipped',
    'meta_locked',
    'favorited_at',
    'checksum',
    'content_id',
    'phash',
    'embedded_at',
    'duplicate_group_id',
    'dup_score',
    'dup_dismissed_at',
    'taken_at',
    'processed_at',
])]
#[ObservedBy(PhotoObserver::class)]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, SharesWithUsers, SoftDeletes;

    /** Photos are owned via the existing uploaded_by column. */
    public function ownerColumn(): string
    {
        return 'uploaded_by';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'processed_at' => 'datetime',
            'favorited_at' => 'datetime',
            'phash' => 'integer',
            'embedded_at' => 'datetime',
            'dup_score' => 'float',
            'dup_dismissed_at' => 'datetime',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
            'place_details' => 'array',
            'rotation' => 'integer',
            'flipped' => 'boolean',
            'meta_locked' => 'boolean',
        ];
    }

    /**
     * Media counts for the whole (non-trashed) library.
     *
     * @return array{total: int, images: int, videos: int, motion: int}
     */
    public static function counts(): array
    {
        $videos = static::query()->where('media_type', 'video')->count();
        $motion = static::query()->whereNotNull('motion_path')->count();
        $duplicates = static::query()->whereNotNull('duplicate_group_id')->count();
        $total = static::query()->count();

        return [
            'total' => $total,
            'images' => $total - $videos,
            'videos' => $videos,
            'motion' => $motion,
            'duplicates' => $duplicates,
        ];
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    /** Whether the photo carries a non-destructive edit (rotation or flip). */
    public function isTransformed(): bool
    {
        return ((int) $this->rotation) % 360 !== 0 || (bool) $this->flipped;
    }

    public function hasMotion(): bool
    {
        return $this->motion_path !== null;
    }

    /**
     * The duration formatted as m:ss (or h:mm:ss), for videos.
     */
    public function durationForHumans(): ?string
    {
        $s = (int) $this->duration;
        if ($s <= 0) {
            return null;
        }

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    /** Video frame rate, e.g. "60 fps". */
    public function fps(): ?string
    {
        $stream = $this->videoStream();
        if ($stream === null || ! isset($stream['r_frame_rate']) || ! str_contains((string) $stream['r_frame_rate'], '/')) {
            return null;
        }

        [$n, $d] = array_map('intval', explode('/', (string) $stream['r_frame_rate']));

        return $d > 0 ? round($n / $d).' fps' : null;
    }

    /** Video codec label, e.g. "HEVC". */
    public function codec(): ?string
    {
        $stream = $this->videoStream();

        return isset($stream['codec_name']) ? strtoupper((string) $stream['codec_name']) : null;
    }

    /** Focal length, e.g. "24 mm" (35mm-equivalent when available). */
    public function focalLength(): ?string
    {
        $exif = $this->exifSection();
        $focal = $exif['FocalLengthIn35mmFilm'] ?? null;
        $focal = is_numeric($focal) ? (int) $focal : (int) round($this->rational($exif['FocalLength'] ?? '') ?? 0);

        return $focal > 0 ? $focal.' mm' : null;
    }

    /** Aperture, e.g. "f/1.7". */
    public function aperture(): ?string
    {
        $f = $this->rational($this->exifSection()['FNumber'] ?? '');

        return $f !== null && $f > 0 ? 'f/'.rtrim(rtrim(number_format($f, 1), '0'), '.') : null;
    }

    /** Shutter speed, e.g. "1/459 s". */
    public function shutter(): ?string
    {
        $e = $this->rational($this->exifSection()['ExposureTime'] ?? '');
        if ($e === null || $e <= 0) {
            return null;
        }

        return $e < 1 ? '1/'.(int) round(1 / $e).' s' : rtrim(rtrim(number_format($e, 1), '0'), '.').' s';
    }

    /** ISO sensitivity, e.g. "ISO 25". */
    public function iso(): ?string
    {
        $iso = $this->exifSection()['ISOSpeedRatings'] ?? null;

        return is_numeric($iso) && (int) $iso > 0 ? 'ISO '.(int) $iso : null;
    }

    /**
     * The first video stream from the stored metadata dump, if any.
     *
     * @return ?array<string, mixed>
     */
    private function videoStream(): ?array
    {
        if (! is_array($this->metadata)) {
            return null;
        }

        foreach ($this->metadata['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                return $stream;
            }
        }

        return null;
    }

    /**
     * The EXIF section from the stored metadata dump.
     *
     * @return array<string, mixed>
     */
    private function exifSection(): array
    {
        return is_array($this->metadata) ? ($this->metadata['EXIF'] ?? []) : [];
    }

    /**
     * Parse an EXIF rational ("168/100") or plain number into a float.
     */
    private function rational(mixed $value): ?float
    {
        if (is_string($value) && str_contains($value, '/')) {
            [$n, $d] = array_map('floatval', explode('/', $value, 2));

            return $d != 0.0 ? $n / $d : null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * The reverse-geocoded address as ordered, readable lines: street, postcode
     * with locality, county/state, and country. Falls back to splitting the
     * display name when structured parts are missing.
     *
     * @return list<string>
     */
    public function placeLines(): array
    {
        $a = is_array($this->place_details) ? $this->place_details : [];

        if ($a === []) {
            return $this->place !== null ? array_map('trim', explode(',', $this->place)) : [];
        }

        $pick = static fn (array $keys): ?string => collect($keys)
            ->map(fn (string $k): ?string => $a[$k] ?? null)
            ->first(fn (?string $v): bool => is_string($v) && $v !== '');

        $street = trim(($pick(['road', 'pedestrian', 'footway', 'path']) ?? '').' '.($a['house_number'] ?? ''));
        $locality = $pick(['city', 'town', 'village', 'municipality', 'hamlet', 'suburb']);
        $postcode = trim(($a['postcode'] ?? '').' '.($locality ?? ''));

        $lines = [
            $street,
            $postcode,
            $pick(['county', 'state_district']),
            $a['state'] ?? null,
            $a['country'] ?? null,
        ];

        return array_values(array_filter(array_map(
            static fn (?string $v): string => trim((string) $v),
            $lines,
        ), static fn (string $v): bool => $v !== ''));
    }

    public function isFavorite(): bool
    {
        return $this->favorited_at !== null;
    }

    /**
     * All object-storage paths belonging to this photo.
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        return array_values(array_filter([$this->disk_path, $this->thumb_path, $this->medium_path, $this->motion_path]));
    }
}
