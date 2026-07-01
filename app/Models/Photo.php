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
    'disk_path',
    'thumb_path',
    'medium_path',
    'mime_type',
    'size',
    'width',
    'height',
    'latitude',
    'longitude',
    'camera',
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
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
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
        return array_values(array_filter([$this->disk_path, $this->thumb_path, $this->medium_path]));
    }
}
