<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a shared folder's content blob (shared-folders/{blob}).
 * Scoped to a vault (vault_id) for member access; owner_id = folder owner for
 * quota. Bytes are client ciphertext; the server never reads them.
 */
#[Fillable(['blob', 'vault_id', 'owner_id', 'size', 'created_at'])]
class SharedFolderBlob extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'blob';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
