<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public inbound upload link (owner-scoped for management; the public routes
 * resolve it by token with the scope removed). The token is the capability.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $file_folder_id
 * @property string $token
 * @property string|null $label
 * @property Carbon|null $expires_at
 */
class FileUploadLink extends Model
{
    use OwnsUserData;

    /** Server-set: token/user_id/file_folder_id are never mass-assigned. */
    protected $fillable = ['label', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return BelongsTo<FileFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'file_folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
