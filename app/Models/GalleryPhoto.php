<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single gallery photo/video (Gallery core pivot). The original bytes plus the
 * server-generated renditions (thumb/medium/motion) live plaintext on the file
 * disk at the *_path columns; this row holds the plaintext metadata. Rows are
 * private per user via OwnsUserData.
 *
 * Byte-derived fields (kind/mime/size/width/height/phash/taken_at/lat/lng/camera
 * and every *_path) are server-set by the processor and never mass-assigned;
 * fillable is only the user-editable favorite/description.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $taken_at
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $camera
 * @property int|null $phash
 * @property bool $favorite
 * @property string|null $description
 * @property string $storage_path
 * @property string|null $thumb_path
 * @property string|null $medium_path
 * @property string|null $motion_path
 * @property array<string, mixed>|null $exif
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class GalleryPhoto extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** Only the user-editable fields are mass-assignable; byte metadata is forceFill'd. */
    protected $fillable = ['favorite', 'description'];

    protected $casts = [
        'exif' => 'array',
        'favorite' => 'boolean',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'phash' => 'integer',
        'taken_at' => 'datetime',
        'lat' => 'decimal:6',
        'lng' => 'decimal:6',
        'version' => 'integer',
    ];

    /**
     * @return BelongsToMany<GalleryAlbum, $this>
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(GalleryAlbum::class, 'gallery_album_photo')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * Every disk path this row references (original + renditions), non-null.
     *
     * @return list<string>
     */
    public function storagePaths(): array
    {
        return array_values(array_filter([
            $this->storage_path,
            $this->thumb_path,
            $this->medium_path,
            $this->motion_path,
        ], static fn ($p): bool => is_string($p) && $p !== ''));
    }
}
