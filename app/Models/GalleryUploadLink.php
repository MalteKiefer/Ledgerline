<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public, token-addressed link that lets anonymous guests contribute photos
 * into one of the owner's albums. Owner-scoped for management; resolved by
 * token (scope removed) for the public endpoints. The password hash is never
 * serialized.
 *
 * @property int $id
 * @property int $user_id
 * @property int $gallery_album_id
 * @property string $token
 * @property string|null $label
 * @property string|null $password_hash
 * @property Carbon|null $expires_at
 */
#[Hidden(['password_hash'])]
class GalleryUploadLink extends Model
{
    use OwnsUserData;

    protected $fillable = ['label'];

    protected $casts = ['expires_at' => 'datetime'];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function needsPassword(): bool
    {
        return $this->password_hash !== null && $this->password_hash !== '';
    }

    /** @return BelongsTo<GalleryAlbum, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
