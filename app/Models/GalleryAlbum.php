<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A gallery album (Gallery core pivot). Groups photos through the
 * gallery_album_photo pivot; cover_photo_id names the album's cover. Rows are
 * private per user via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $cover_photo_id
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class GalleryAlbum extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'cover_photo_id'];

    protected $casts = [
        'cover_photo_id' => 'integer',
        'version' => 'integer',
    ];

    /**
     * @return BelongsToMany<GalleryPhoto, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(GalleryPhoto::class, 'gallery_album_photo')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('gallery_album_photo.position');
    }
}
