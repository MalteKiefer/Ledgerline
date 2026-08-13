<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One detected face in a photo (owner-scoped). Holds the normalised bounding
 * box, a saved square crop, and — on pgvector — a 512-dim face embedding used to
 * group faces into people. gallery_person_id is the assigned person (nullable).
 *
 * @property int $id
 * @property int $user_id
 * @property int $gallery_photo_id
 * @property int|null $gallery_person_id
 * @property array<int, float> $box
 * @property float $score
 * @property string|null $crop_path
 * @property bool $hidden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GalleryFace extends Model
{
    use OwnsUserData;

    protected $fillable = [];

    protected $casts = [
        'gallery_photo_id' => 'integer',
        'gallery_person_id' => 'integer',
        'box' => 'array',
        'score' => 'float',
        'hidden' => 'boolean',
    ];

    /** @return BelongsTo<GalleryPerson, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(GalleryPerson::class, 'gallery_person_id');
    }

    /** @return BelongsTo<GalleryPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(GalleryPhoto::class, 'gallery_photo_id');
    }
}
