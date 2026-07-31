<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a stored content blob (files/{blob}). One row per blob
 * the user uploaded; drives the per-user storage quota and access control, and
 * lets a reconcile/sweep reclaim bytes the sealed manifest no longer references.
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class FileBlob extends Model
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
