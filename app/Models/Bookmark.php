<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A bookmark. Plain database row; trashing is Laravel soft-deletion. */
#[Fillable(['bookmark_folder_id', 'title', 'url', 'description', 'tags', 'favorite'])]
class Bookmark extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'favorite' => 'boolean',
        ];
    }

    /** @return BelongsTo<BookmarkFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(BookmarkFolder::class, 'bookmark_folder_id');
    }
}
