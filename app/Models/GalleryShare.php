<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public, unauthenticated gallery share link (album or single photo).
 *
 * Owner-scoped explicitly in the controllers (NO global read scope) so the
 * public /gallery-share/{token} routes can resolve a link by token without —
 * or in spite of — an authenticated session. Bytes are served plaintext; the
 * optional password is a rate-limited access gate, not an encryption root.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $kind
 * @property int|null $gallery_album_id
 * @property int|null $gallery_photo_id
 * @property string|null $password_hash
 * @property bool $allow_download
 * @property Carbon|null $expires_at
 * @property int $version
 */
class GalleryShare extends Model
{
    /** Server-set: token/user_id/kind/targets set explicitly, never mass-assigned. */
    protected $fillable = ['allow_download', 'expires_at'];

    protected $casts = [
        'allow_download' => 'boolean',
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function needsPassword(): bool
    {
        return $this->password_hash !== null && $this->password_hash !== '';
    }

    /**
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }

    /**
     * @return BelongsTo<GalleryPhoto, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(GalleryPhoto::class, 'gallery_photo_id');
    }
}
