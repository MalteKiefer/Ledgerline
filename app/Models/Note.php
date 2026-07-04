<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A markdown note, private to its owning user. Trashing is Laravel soft-deletion. */
#[Fillable(['title', 'content', 'tags', 'pinned'])]
class Note extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'pinned' => 'boolean',
        ];
    }
}
