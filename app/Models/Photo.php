<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A gallery photo. Bytes live under a date-structured object-storage prefix
 * (original + thumbnail + medium rendition); this row holds only metadata.
 * Sorted by capture date (EXIF DateTimeOriginal, or the upload time).
 */
#[Fillable([
    'uuid',
    'name',
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
    'camera',
    'metadata',
    'rotation',
    'flipped',
    'meta_locked',
    'favorited_at',
    'checksum',
    'taken_at',
    'processed_at',
])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'processed_at' => 'datetime',
            'favorited_at' => 'datetime',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
            'rotation' => 'integer',
            'flipped' => 'boolean',
            'meta_locked' => 'boolean',
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

    /**
     * A short technical summary from the stored metadata dump: frame rate and
     * codec for videos, focal length / aperture / shutter / ISO for images.
     */
    public function techLine(): ?string
    {
        $meta = $this->metadata;
        if (! is_array($meta)) {
            return null;
        }

        return $this->isVideo() ? $this->videoTech($meta) : $this->imageTech($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function videoTech(array $meta): ?string
    {
        $video = null;
        foreach ($meta['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                $video = $stream;
                break;
            }
        }

        if ($video === null) {
            return null;
        }

        $parts = [];
        if (isset($video['r_frame_rate']) && str_contains((string) $video['r_frame_rate'], '/')) {
            [$n, $d] = array_map('intval', explode('/', (string) $video['r_frame_rate']));
            if ($d > 0) {
                $parts[] = round($n / $d).' fps';
            }
        }
        if (isset($video['codec_name'])) {
            $parts[] = strtoupper((string) $video['codec_name']);
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function imageTech(array $meta): ?string
    {
        $exif = $meta['EXIF'] ?? [];
        $rational = static function (mixed $v): ?float {
            if (is_string($v) && str_contains($v, '/')) {
                [$n, $d] = array_map('floatval', explode('/', $v, 2));

                return $d != 0.0 ? $n / $d : null;
            }

            return is_numeric($v) ? (float) $v : null;
        };

        $parts = [];

        $focal = $exif['FocalLengthIn35mmFilm'] ?? null;
        $focal = is_numeric($focal) ? (int) $focal : (int) round($rational($exif['FocalLength'] ?? '') ?? 0);
        if ($focal > 0) {
            $parts[] = $focal.'mm';
        }

        if (($f = $rational($exif['FNumber'] ?? '')) !== null && $f > 0) {
            $parts[] = 'f/'.rtrim(rtrim(number_format($f, 1), '0'), '.');
        }

        if (($e = $rational($exif['ExposureTime'] ?? '')) !== null && $e > 0) {
            $parts[] = $e < 1 ? '1/'.(int) round(1 / $e).'s' : $e.'s';
        }

        $iso = $exif['ISOSpeedRatings'] ?? null;
        if (is_numeric($iso) && (int) $iso > 0) {
            $parts[] = 'ISO '.(int) $iso;
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isFavorite(): bool
    {
        return $this->favorited_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
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
