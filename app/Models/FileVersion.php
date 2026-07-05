<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prior blob of a StoredFile, snapshotted when the file's content changed on
 * sync. Downloadable as a safety net; the newest N are kept per file.
 */
#[Fillable(['id', 'file_id', 'user_id', 'name', 'mime', 'size', 'blob', 'created_at'])]
class FileVersion extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }
}
