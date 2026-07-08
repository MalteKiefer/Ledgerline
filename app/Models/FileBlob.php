<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership record for an uploaded raw blob (files/{blob}) before it is attached
 * to a StoredFile via the manifest sync. See the create migration for why.
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
