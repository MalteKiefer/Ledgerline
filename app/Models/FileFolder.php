<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A folder in the plaintext-relational file tree (Files core pivot). Rows are
 * private per user via OwnsUserData; self-referential parent forms the tree.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class FileFolder extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'parent_id'];

    protected $casts = [
        'version' => 'integer',
    ];

    /** @return HasMany<FileFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<FileEntry, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(FileEntry::class, 'file_folder_id');
    }
}
