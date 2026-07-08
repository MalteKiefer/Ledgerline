<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SharesWithUsers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user-owned photo album: a named, ordered collection of photos, shareable
 * with other users (ResourceShare) and via a public link (PublicShare).
 */
// user_id is set by the AssignsOwner creating hook (unfakeable), never mass-assigned.
#[Fillable(['name', 'cover_photo_id'])]
class Album extends Model
{
    use HasUuids;
    use SharesWithUsers;

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'album_photo')
            ->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }
}
