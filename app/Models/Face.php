<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single detected face: a bounding box + detection score on a photo, a stored
 * crop thumbnail, and (on Postgres) a face embedding used for clustering.
 */
#[Fillable([
    'photo_id',
    'person_id',
    'det_score',
    'box_x1',
    'box_y1',
    'box_x2',
    'box_y2',
    'thumb_path',
    'pinned',
])]
class Face extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'det_score' => 'float',
            'box_x1' => 'float',
            'box_y1' => 'float',
            'box_x2' => 'float',
            'box_y2' => 'float',
            'pinned' => 'boolean',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
