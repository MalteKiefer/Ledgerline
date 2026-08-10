<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wikilink edge (source note → target title / resolved target note). Owner-scoped
 * via OwnsUserData; no soft-delete/version — edges are derived from a note's body
 * and fully rebuilt on each save.
 *
 * @property int $id
 * @property int $user_id
 * @property int $source_note_id
 * @property int|null $target_note_id
 * @property string $target_title
 */
class NoteLink extends Model
{
    use OwnsUserData;

    protected $fillable = ['source_note_id', 'target_note_id', 'target_title'];

    /** @return BelongsTo<Note, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'source_note_id');
    }

    /** @return BelongsTo<Note, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'target_note_id');
    }
}
