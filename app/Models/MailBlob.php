<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a stored mail message blob (mail/{blob}) — the sealed
 * RFC822 bytes. One row per blob a message references; drives quota,
 * owner-scoped access, and lets a reconcile/sweep reclaim bytes no message
 * row references anymore. Mirrors ContactBlob/GalleryBlob exactly.
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class MailBlob extends Model
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
