<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of a backup job — its status, timing, size and any error.
 */
#[Fillable([
    'backup_job_id', 'status', 'started_at', 'finished_at', 'bytes', 'filename', 'message', 'log',
])]
class BackupRun extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'bytes' => 'integer',
        ];
    }

    /** Run duration in whole seconds, or null while still running. */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at, absolute: true);
    }

    /** @return BelongsTo<BackupJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(BackupJob::class, 'backup_job_id');
    }
}
