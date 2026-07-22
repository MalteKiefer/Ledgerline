<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a stored Explore track blob (explore/{blob}). One row per
 * blob the user uploaded; drives quota, owner-scoped access, and lets a
 * reconcile/sweep reclaim bytes the sealed explore module store no longer
 * references. The track/coupling/tolerance records themselves live sealed in the
 * `explore` module store — only the optional raw track files land here as opaque
 * ciphertext blobs. The server holds no location data in the clear.
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class ExploreBlob extends Model
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
