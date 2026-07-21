<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A shared password-Tresor container. The UUID primary key and all cleartext
 * metadata are assigned server-side; the sealed manifest lives in the
 * SharedVaultStore relation. The server holds no item data or vault name.
 *
 * owner_id is stamped on creation from the authenticated user; it is NOT
 * mass-assignable (not in $fillable) so it cannot be spoofed via request input.
 *
 * @property string $id
 * @property string $kind
 * @property int|null $owner_id
 */
class SharedVault extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [];

    protected static function booted(): void
    {
        static::creating(function (self $vault): void {
            if (empty($vault->id)) {
                $vault->id = (string) Str::uuid();
            }

            if ($vault->owner_id === null && Auth::check()) {
                $vault->owner_id = Auth::id();
            }

            // kind is server-assigned only (never from request input); default to
            // a password vault so the existing sharing path is unchanged.
            if (empty($vault->kind)) {
                $vault->kind = 'password';
            }
        });
    }

    /**
     * All membership rows for this vault.
     *
     * @return HasMany<SharedVaultMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(SharedVaultMember::class, 'vault_id');
    }

    /** The sealed manifest store for this vault (one-to-one). */
    public function store(): HasOne
    {
        return $this->hasOne(SharedVaultStore::class, 'vault_id');
    }
}
