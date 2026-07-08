<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A bookmark, private to its owning user. Trashing is Laravel soft-deletion. */
#[Fillable(['bookmark_folder_id', 'title', 'url', 'description', 'tags', 'favorite', 'read_later', 'read_at', 'last_checked_at', 'dead_at', 'enc_bookmark', 'is_encrypted'])]
class Bookmark extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'favorite' => 'boolean',
            'read_later' => 'boolean',
            'read_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'dead_at' => 'datetime',
            'is_encrypted' => 'boolean',
        ];
    }

    /** @return BelongsTo<BookmarkFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(BookmarkFolder::class, 'bookmark_folder_id');
    }
}
