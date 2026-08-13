<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cross-user gallery share (viewer-only): the owner grants a registered user
 * access to EITHER one of their albums (gallery_album_id) OR their whole gallery
 * (gallery_album_id null). Owner-private via OwnsUserData (owner column
 * `owner_id`); the recipient side resolves by recipient_id with the scope
 * removed and never mutates the owner's photos.
 *
 * @property int $id
 * @property int $owner_id
 * @property int $recipient_id
 * @property int|null $gallery_album_id
 * @property string $role
 * @property Carbon|null $created_at
 */
class GalleryInternalShare extends Model
{
    use OwnsUserData;

    protected $fillable = [];

    public function ownerColumn(): string
    {
        return 'owner_id';
    }

    /** True when this share targets one album rather than the whole gallery. */
    public function isAlbum(): bool
    {
        return $this->gallery_album_id !== null;
    }

    /** Editor shares of a specific ALBUM may contribute photos (collaborative album). */
    public function canContribute(): bool
    {
        return $this->role === 'editor' && $this->isAlbum();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
