<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The per-user opaque zero-knowledge store row (one sealed workspace manifest).
 * The server never reads inside `ciphertext`; `version` drives optimistic
 * concurrency so two tabs can't silently clobber each other.
 */
class VaultStore extends Model
{
    protected $table = 'vault_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'ciphertext', 'version'];
}
