<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single file (Files core pivot). Bytes live plaintext on the file disk at
 * storage_path; this row holds the plaintext metadata + version history. Rows
 * are private per user via OwnsUserData. Table is `files` (class name avoids a
 * clash with the framework File facade/helper).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $file_folder_id
 * @property string $name
 * @property string|null $mime
 * @property int $size
 * @property string $storage_path
 * @property string|null $sha256
 * @property array<int, string>|null $tags
 * @property string|null $note
 * @property bool $favorite
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class FileEntry extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $table = 'files';

    /** Server-set fields (size/storage_path/sha256/mime) are never mass-assigned. */
    protected $fillable = ['name', 'file_folder_id', 'tags', 'note', 'favorite'];

    /** Extracted full-text index; queried server-side, never serialized (can be ~1 MiB). */
    protected $hidden = ['search_text'];

    protected $casts = [
        'tags' => 'array',
        'favorite' => 'boolean',
        'size' => 'integer',
        'version' => 'integer',
    ];

    /** @return HasMany<FileVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class, 'file_id');
    }

    /** @return BelongsToMany<FileLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(FileLabel::class, 'file_label_file', 'file_id', 'file_label_id');
    }
}
