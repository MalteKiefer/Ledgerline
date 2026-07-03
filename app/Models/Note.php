<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A markdown note. Plain database row (not zero-knowledge). */
#[Fillable(['title', 'content', 'tags', 'pinned', 'trashed_at'])]
class Note extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'pinned' => 'boolean',
            'trashed_at' => 'datetime',
        ];
    }
}
