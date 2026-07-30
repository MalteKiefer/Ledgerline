<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A prior revision of a file's bytes (Files core pivot). Not user-scoped
 * directly — access is always mediated through the owning FileEntry (which is
 * owner-scoped). Has a created_at only (no updated_at).
 *
 * @property int $id
 * @property int $file_id
 * @property string $storage_path
 * @property int $size
 * @property string|null $mime
 * @property string|null $sha256
 * @property Carbon|null $created_at
 */
class FileVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['storage_path', 'size', 'mime', 'sha256', 'created_at'];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<FileEntry, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class, 'file_id');
    }
}
