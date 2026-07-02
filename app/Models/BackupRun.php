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
    'backup_job_id', 'status', 'started_at', 'finished_at', 'bytes', 'filename', 'message',
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

    /** @return BelongsTo<BackupJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(BackupJob::class, 'backup_job_id');
    }
}
