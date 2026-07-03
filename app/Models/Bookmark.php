<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bookmark. Plain database row (not zero-knowledge). */
#[Fillable(['bookmark_folder_id', 'title', 'url', 'description', 'tags', 'favorite', 'trashed_at'])]
class Bookmark extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'favorite' => 'boolean',
            'trashed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BookmarkFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(BookmarkFolder::class, 'bookmark_folder_id');
    }
}
