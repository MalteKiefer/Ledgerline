<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A plaintext cross-user share (pivot). The owner grants other registered users
 * access to EITHER one of their own file_folders (and its whole subtree, via
 * file_folder_id) OR a single one of their files (via file_id) — exactly one of
 * the two columns is set. Recipients + roles live on folder_share_members.
 *
 * Rows are owner-private via OwnsUserData (owner column is `owner_id`): the owner
 * lists/manages only their own shares and route-model binding auto-404s a
 * stranger. The member side (SharedWithMeController) resolves a share by id with
 * the scope removed and gates every access through FolderSharePolicy.
 *
 * @property int $id
 * @property int $owner_id
 * @property int|null $file_folder_id
 * @property int|null $file_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FolderShare extends Model
{
    use OwnsUserData;

    /** owner_id / file_folder_id / file_id are set explicitly, never mass-assigned. */
    protected $fillable = [];

    /** The share is owned via `owner_id`, not the default `user_id`. */
    public function ownerColumn(): string
    {
        return 'owner_id';
    }

    /** True when this share targets a single file (file_id set) rather than a folder subtree. */
    public function isFile(): bool
    {
        return $this->file_id !== null;
    }

    /** Discriminator for API payloads / clients: 'file' or 'folder'. */
    public function kind(): string
    {
        return $this->isFile() ? 'file' : 'folder';
    }

    /**
     * @return HasMany<FolderShareMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FolderShareMember::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<FileFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'file_folder_id');
    }

    /**
     * @return BelongsTo<FileEntry, $this>
     */
    public function sharedFile(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class, 'file_id');
    }
}
