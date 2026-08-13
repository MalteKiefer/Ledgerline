<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A note (plaintext-relational). Title + Markdown body + tags are stored
 * plaintext + indexed so the server can search/sort. Rows are private per user
 * via OwnsUserData; optimistic `version` guards the rare concurrent-tab edit.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $note_folder_id
 * @property string|null $title
 * @property string|null $body
 * @property list<string>|null $tags
 * @property bool $pinned
 * @property bool $favorite
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Note extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['note_folder_id', 'title', 'body', 'tags', 'pinned', 'favorite'];

    protected $casts = [
        'tags' => 'array',
        'pinned' => 'boolean',
        'favorite' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsTo<NoteFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(NoteFolder::class, 'note_folder_id');
    }
}
