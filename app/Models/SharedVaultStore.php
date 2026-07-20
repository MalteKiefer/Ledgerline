<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The opaque sealed manifest for a shared password-Tresor. One row per vault;
 * vault_id is both the FK and the primary key. version drives optimistic
 * concurrency so two concurrent saves cannot silently clobber each other.
 * The server never reads inside sealed_manifest.
 */
#[Fillable(['vault_id', 'sealed_manifest', 'version'])]
class SharedVaultStore extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'vault_id';

    protected $keyType = 'string';
}
