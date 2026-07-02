<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A remote storage target for backups (S3, Backblaze B2, SFTP or WebDAV).
 *
 * The driver config (bucket/keys or host/credentials) is stored as an encrypted
 * JSON blob — usable in the clear at runtime, unreadable in a database dump.
 */
#[Fillable(['name', 'driver', 'config'])]
class BackupDestination extends Model
{
    public const DRIVERS = ['s3', 'b2', 'sftp', 'webdav'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
        ];
    }

    /** @return HasMany<BackupJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }
}
