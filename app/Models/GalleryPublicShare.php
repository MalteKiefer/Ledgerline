<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public, unauthenticated gallery share link for one album. Rows are
 * owner-private via OwnsUserData; the public /gallery-share/{token} routes
 * resolve by sha256(token) with the scope removed. Bytes are served plaintext
 * with a sandbox CSP; the optional password is a rate-limited access gate and
 * download (attachment) is gated by allow_download.
 *
 * @property int $id
 * @property int $user_id
 * @property int $gallery_album_id
 * @property string $token
 * @property string|null $token_hash
 * @property string|null $password_hash
 * @property bool $allow_download
 * @property Carbon|null $expires_at
 * @property int $version
 */
class GalleryPublicShare extends Model
{
    use OwnsUserData;

    /** Server-set: token/user_id/album set explicitly, never mass-assigned. */
    protected $fillable = ['allow_download', 'expires_at'];

    protected $hidden = ['password_hash', 'token_hash'];

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
     * Setting the plaintext token also derives sha256(token) so public links
     * resolve by hash — a DB/backup leak yields no directly-usable link.
     *
     * @return Attribute<string, string>
     */
    protected function token(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => ['token' => $value, 'token_hash' => hash('sha256', $value)],
        );
    }

    /**
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
