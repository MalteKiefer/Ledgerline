<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person: a cluster of detected faces (owner-scoped). Unnamed until the owner
 * names it; cover_face_id points at the face crop shown as the person's avatar.
 * Optionally linked to an address-book contact (contact_id).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string|null $contact_id
 * @property int|null $cover_face_id
 * @property bool $hidden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GalleryPerson extends Model
{
    use OwnsUserData;

    protected $fillable = ['name'];

    protected $casts = [
        'cover_face_id' => 'integer',
        'hidden' => 'boolean',
    ];

    /** @return HasMany<GalleryFace, $this> */
    public function faces(): HasMany
    {
        return $this->hasMany(GalleryFace::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
