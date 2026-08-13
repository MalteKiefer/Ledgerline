<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A folder in the plaintext-relational notes tree. Rows are private per user via
 * OwnsUserData; self-referential parent forms the tree.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 * @property int $position
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class NoteFolder extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'parent_id', 'color', 'position'];

    protected $casts = [
        'position' => 'integer',
        'version' => 'integer',
    ];

    /** @return HasMany<NoteFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'note_folder_id');
    }
}
