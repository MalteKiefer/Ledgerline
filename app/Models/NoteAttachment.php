<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file/image attachment on a note. Owner-scoped via OwnsUserData; the bytes
 * live plaintext on the files disk at `blob_path` (notes/{uuid}).
 *
 * @property int $id
 * @property int $note_id
 * @property int $user_id
 * @property string $blob_path
 * @property string $name
 * @property string|null $mime
 * @property int $size
 */
class NoteAttachment extends Model
{
    use OwnsUserData;

    protected $fillable = ['note_id', 'name', 'mime', 'size', 'blob_path'];

    protected $casts = [
        'size' => 'integer',
    ];

    /** @return BelongsTo<Note, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }
}
