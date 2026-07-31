<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A single-use, expiring invite / password-reset link. The token is never stored in
 * the clear — only its SHA-256 hash. Consuming it sets the target user's password.
 *
 * @property int $user_id
 * @property string $token_hash
 * @property ?Carbon $expires_at
 * @property ?Carbon $used_at
 */
class InviteLink extends Model
{
    /** Nothing is mass-assignable: every attribute is set server-side via forceFill. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** The allowed link lifetimes in hours (admin picks one). */
    public const TTL_HOURS = [1, 24, 168, 720];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hash a plaintext token for storage / comparison (high-entropy → fast hash is fine). */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** A fresh URL-safe token (256 bits). */
    public static function newToken(): string
    {
        return Str::random(48);
    }

    /** Still usable: not consumed and not expired. */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /** Constant-time check that a presented token matches this link. */
    public function matches(string $token): bool
    {
        return hash_equals($this->token_hash, self::hashToken($token));
    }
}
