<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One detected face inside a gallery photo. The square crop lives plaintext on
 * the file disk at crop_path; the CLIP face embedding (pgvector, Postgres only)
 * is stored raw and never cast/serialised so sqlite (no column) is unaffected.
 * Rows are private per user via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property int $gallery_photo_id
 * @property int|null $gallery_person_id
 * @property float $score
 * @property array{0: float, 1: float, 2: float, 3: float} $box
 * @property string|null $crop_path
 * @property bool $hidden
 * @property Carbon|null $created_at
 */
class GalleryFace extends Model
{
    use OwnsUserData;

    protected $fillable = [];

    /** The pgvector column is written/read via raw SQL, never through the model. */
    protected $hidden = ['embedding'];

    protected $casts = [
        'box' => 'array',
        'score' => 'float',
        'hidden' => 'boolean',
        'gallery_photo_id' => 'integer',
        'gallery_person_id' => 'integer',
    ];

    /**
     * @return BelongsTo<GalleryPhoto, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(GalleryPhoto::class, 'gallery_photo_id');
    }

    /**
     * @return BelongsTo<GalleryPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(GalleryPerson::class, 'gallery_person_id');
    }
}
