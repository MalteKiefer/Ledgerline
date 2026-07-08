<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A public, tokenised read-only link to a photo album. Anyone with the token can
 * view it without an account. Optionally time-boxed (expires_at) and
 * password-gated (hashed).
 */
#[Fillable(['token', 'owner_id', 'shareable_type', 'shareable_id', 'expires_at', 'password'])]
class PublicShare extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /** True once the optional expiry has passed. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Get (or create) the public link for a resource. */
    public static function forResource(Model $resource, int $ownerId): self
    {
        return static::firstOrCreate(
            ['shareable_type' => $resource->getMorphClass(), 'shareable_id' => $resource->getKey()],
            ['token' => Str::random(48), 'owner_id' => $ownerId],
        );
    }

    /** The public URL: the album's HTML page (albums are the only public type). */
    public function url(): string
    {
        return route('public-share.album', $this->token);
    }
}
