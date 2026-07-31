<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ownership ledger for a stored passwords shard blob (passwords/{blob}). Drives the
 * shard-reference integrity guard + reconcile/sweep. Mirrors NoteBlob / FileBlob.
 *
 * @property string $blob
 * @property int $user_id
 * @property int $size
 * @property Carbon|null $created_at
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class PasswordBlob extends Model
{
    protected $table = 'passwords_blobs';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'blob';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
