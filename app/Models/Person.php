<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person: a cluster of detected faces (naming is free-text).
 */
#[Fillable([
    'user_id',
    'name',
    'cover_face_id',
    'hidden_at',
    'faces_count',
])]
class Person extends Model
{
    use HasUuids;
    use OwnsUserData;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
            'faces_count' => 'integer',
        ];
    }

    public function faces(): HasMany
    {
        return $this->hasMany(Face::class);
    }
}
