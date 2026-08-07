<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A coloured, user-defined label for files (a many-to-many taxonomy, distinct
 * from the free-text `tags` column). Owner-scoped via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FileLabel extends Model
{
    use OwnsUserData;

    protected $fillable = ['name', 'color'];

    /** @return BelongsToMany<FileEntry, $this> */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(FileEntry::class, 'file_label_file', 'file_label_id', 'file_id');
    }
}
