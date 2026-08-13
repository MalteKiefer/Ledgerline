<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user group: a reusable device-cap limit template and an
 * optional shareable flag (members may offer it as a share target). Limits are
 * non-secret metadata; a user may belong to many groups and the most generous
 * group limit applies after any per-user override.
 *
 * @property ?int $max_connected_devices
 * @property bool $shareable
 * @property ?array<int, string> $modules
 */
#[Fillable(['name', 'max_connected_devices', 'shareable', 'modules'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'max_connected_devices' => 'integer',
            'shareable' => 'boolean',
            'modules' => 'array',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
