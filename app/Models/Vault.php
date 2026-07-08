<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * A user's zero-knowledge encryption vault row. Stores only opaque ciphertext
 * and key-derivation parameters; never the passphrase or the vault key. One row
 * per user (user_id is stamped server-side, never mass-assigned).
 */
#[Fillable([
    'salt',
    'kdf_ops',
    'kdf_mem',
    'wrapped_vault_key',
    'wrap_nonce',
    'wrapped_vault_key_recovery',
    'recovery_nonce',
])]
class Vault extends Model
{
    /** The current user's vault row, if they have set up encryption. */
    public static function current(): ?self
    {
        $uid = Auth::id();

        return $uid === null ? null : static::query()->where('user_id', $uid)->first();
    }
}
