<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One scheduled backup task: a source, a destination, a cron schedule, how many
 * versions to keep, optional archive encryption and a notification channel.
 */
#[Fillable([
    'name', 'source', 'backup_destination_id', 'cron', 'retention',
    'encrypt', 'passphrase', 'notify', 'enabled',
])]
class BackupJob extends Model
{
    public const SOURCES = ['database', 'files', 'gallery'];

    public const NOTIFY_CHANNELS = ['none', 'ntfy', 'webhook', 'mail'];

    protected function casts(): array
    {
        return [
            'retention' => 'integer',
            'encrypt' => 'boolean',
            'passphrase' => 'encrypted',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BackupDestination, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'backup_destination_id');
    }

    /** @return HasMany<BackupRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }
}
