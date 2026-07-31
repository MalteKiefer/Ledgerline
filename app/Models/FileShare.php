<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public, unauthenticated Files share link (a single file or a folder subtree).
 *
 * Rows are owner-private via OwnsUserData (the global scope applies whenever a
 * user is authenticated); the public /file-share/{token} routes resolve a link
 * by token with the scope explicitly removed so an anonymous — or logged-in
 * stranger — visitor can open it. Bytes are served plaintext; the optional
 * password is a rate-limited access gate, not an encryption root.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $kind
 * @property int|null $file_id
 * @property int|null $file_folder_id
 * @property string|null $password_hash
 * @property bool $allow_download
 * @property Carbon|null $expires_at
 * @property int $version
 */
class FileShare extends Model
{
    use OwnsUserData;

    /** Server-set: token/user_id/kind/targets set explicitly, never mass-assigned. */
    protected $fillable = ['allow_download', 'expires_at'];

    /** The password hash must never leak through serialization. */
    protected $hidden = ['password_hash'];

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
     * Public read-only flag exposing whether a password gate is set (never the hash).
     *
     * @return Attribute<bool, never>
     */
    protected function hasPassword(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->needsPassword());
    }

    /**
     * @return BelongsTo<FileEntry, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class, 'file_id');
    }

    /**
     * @return BelongsTo<FileFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'file_folder_id');
    }
}
