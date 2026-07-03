<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A markdown note. Plain database row; trashing is Laravel soft-deletion. */
#[Fillable(['title', 'content', 'tags', 'pinned'])]
class Note extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'pinned' => 'boolean',
        ];
    }
}
