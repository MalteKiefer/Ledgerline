<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single Files activity-feed entry (owner-scoped via OwnsUserData). Records
 * who did what to which file/folder — upload, rename, move, trash, restore,
 * delete, new version, share, external upload.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $file_id
 * @property int|null $file_folder_id
 * @property string $action
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property array<string,mixed>|null $meta
 * @property Carbon $created_at
 */
class FileActivity extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $fillable = ['file_id', 'file_folder_id', 'action', 'actor_id', 'actor_name', 'meta'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<FileEntry, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class, 'file_id');
    }
}
