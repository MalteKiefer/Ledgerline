<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ownership ledger for a stored invoices shard blob (invoices/{blob}). Mirrors NoteBlob.
 *
 * @property string $blob
 * @property int $user_id
 * @property int $size
 * @property Carbon|null $created_at
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class InvoiceBlob extends Model
{
    protected $table = 'invoices_blobs';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'blob';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
