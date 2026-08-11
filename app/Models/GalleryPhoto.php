<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A gallery photo (plaintext-relational). Bytes live on the files disk under
 * gallery/{uuid}; this row holds the metadata. Owner-scoped via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $storage_path
 * @property string|null $motion_path
 * @property string|null $poster_path
 * @property string|null $playback_path
 * @property string|null $content_id
 * @property string $name
 * @property string|null $mime
 * @property string $media_type
 * @property string $status
 * @property int|null $duration
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int $rotation
 * @property bool $flip_h
 * @property Carbon|null $taken_at
 * @property string|null $camera
 * @property string|null $place
 * @property float|null $lat
 * @property float|null $lng
 * @property array<string,mixed>|null $exif
 * @property bool $favorite
 * @property string|null $sha256
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class GalleryPhoto extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'favorite'];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
        'embedded_at' => 'datetime',
        'rotation' => 'integer',
        'flip_h' => 'boolean',
        'taken_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'exif' => 'array',
        'favorite' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsToMany<GalleryAlbum, $this> */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(GalleryAlbum::class, 'gallery_album_photo');
    }

    /** @return HasMany<GalleryFace, $this> */
    public function faces(): HasMany
    {
        return $this->hasMany(GalleryFace::class);
    }
}
