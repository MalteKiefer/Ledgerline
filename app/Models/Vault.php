<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single workspace encryption vault row. Stores only opaque ciphertext and
 * key-derivation parameters; never the passphrase or the vault key.
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
    protected $table = 'vault';

    /**
     * The single vault row, if the workspace has set up encryption.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
