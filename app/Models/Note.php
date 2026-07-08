<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SharesWithUsers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A markdown note, private to its owner but shareable with other users. */
#[Fillable(['title', 'content', 'tags', 'pinned', 'enc_note', 'is_encrypted'])]
class Note extends Model
{
    use SharesWithUsers;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'pinned' => 'boolean',
            'is_encrypted' => 'boolean',
        ];
    }
}
