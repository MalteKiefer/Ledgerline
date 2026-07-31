<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a stored gallery content blob (gallery/{blob}). One row
 * per blob the user uploaded; drives quota, owner-scoped access, and lets a
 * reconcile/sweep reclaim bytes the sealed gallery index no longer references.
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class GalleryBlob extends Model
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
