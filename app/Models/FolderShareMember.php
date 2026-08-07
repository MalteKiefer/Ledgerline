<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recipient of a plaintext folder share (pivot). Deliberately carries no
 * owner scope of its own — it is only ever reached through its parent
 * FolderShare, whose access is gated by FolderSharePolicy. folder_share_id and
 * user_id are set explicitly; only `role` (viewer|editor) is mass-assignable.
 *
 * @property int $id
 * @property int $folder_share_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FolderShareMember extends Model
{
    protected $fillable = ['role'];

    /**
     * @return BelongsTo<FolderShare, $this>
     */
    public function share(): BelongsTo
    {
        return $this->belongsTo(FolderShare::class, 'folder_share_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
