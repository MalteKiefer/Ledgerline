<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public, unauthenticated share link for a gallery album.
 *
 * Owner-scoped explicitly in the controllers (no global read scope) so the
 * public routes can resolve a link by token without an authenticated user. The
 * sealed manifest and blob key material are all client-encrypted — this model
 * only carries the ciphertext and the coarse access controls.
 */
#[Fillable(['token', 'user_id', 'kind', 'sealed_manifest', 'blob_refs', 'password_hash', 'allow_download', 'expires_at'])]
class PublicShare extends Model
{
    protected function casts(): array
    {
        return [
            'blob_refs' => 'array',
            'allow_download' => 'boolean',
            'expires_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function needsPassword(): bool
    {
        return $this->password_hash !== null;
    }
}
