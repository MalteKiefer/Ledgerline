<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VaultRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A membership row linking a user to a shared password-Tresor. The
 * wrapped_vault_key column holds the vault key sealed to the recipient's
 * x25519 public key — the server can route this ciphertext but cannot read the
 * vault key. role and status are plain strings (no enum constraint in the DB so
 * the application layer stays in control of valid values).
 */
#[Fillable([
    'vault_id',
    'user_id',
    'role',
    'wrapped_vault_key',
    'recipient_fingerprint',
    'status',
])]
class SharedVaultMember extends Model
{
    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'role' => VaultRole::class,
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(SharedVault::class, 'vault_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
