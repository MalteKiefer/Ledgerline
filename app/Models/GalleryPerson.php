<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A recognised person (face cluster) in the gallery. Faces are grouped to a
 * person by cosine similarity of their CLIP face embeddings; the person starts
 * unnamed and the user can rename or merge clusters. Rows are private per user.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property int|null $cover_face_id
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class GalleryPerson extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** Only the name is user-editable; cover_face_id is server-set. */
    protected $fillable = ['name'];

    protected $casts = [
        'cover_face_id' => 'integer',
        'version' => 'integer',
    ];

    /**
     * @return HasMany<GalleryFace, $this>
     */
    public function faces(): HasMany
    {
        return $this->hasMany(GalleryFace::class);
    }
}
