<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A gallery album (owner-scoped). Groups photos via the gallery_album_photo
 * pivot; cover_photo_id points at one of its photos (nullable).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $cover_photo_id
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GalleryAlbum extends Model
{
    use OwnsUserData;

    protected $fillable = ['name'];

    protected $casts = [
        'cover_photo_id' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsToMany<GalleryPhoto, $this> */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(GalleryPhoto::class, 'gallery_album_photo');
    }
}
