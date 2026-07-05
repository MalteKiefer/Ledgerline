<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A public, tokenised read-only link to a calendar or address book. Anyone with
 * the token can view it without an account.
 */
#[Fillable(['token', 'owner_id', 'shareable_type', 'shareable_id'])]
class PublicShare extends Model
{
    public function shareable(): MorphTo
    {
        return $this->morphTo();
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

    /** The public URL: an ICS feed for calendars, an HTML page for albums, a vCard for address books. */
    public function url(): string
    {
        if ($this->shareable_type === (new Calendar)->getMorphClass()) {
            return route('public-share.ics', $this->token);
        }
        if ($this->shareable_type === (new Album)->getMorphClass()) {
            return route('public-share.album', $this->token);
        }

        return route('public-share.vcf', $this->token);
    }
}
