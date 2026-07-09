<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check every minute which backup jobs are due (per their own cron) and
// dispatch them. Requires the system scheduler (`php artisan schedule:run`).
Schedule::command('backups:run-due')->everyMinute()->withoutOverlapping();

// Refresh the cached Paperless tags / document types / correspondents hourly so
// the transfer modal always has an up-to-date quick-pick list.
Schedule::command('paperless:sync')->hourly()->withoutOverlapping();

// Pull every mail account into the local archive (server-deleted mail is kept).

// Remove expired download exports (past their retention window) and their zips.
Schedule::command('exports:prune')->daily()->withoutOverlapping();

// Fail exports left stuck building by a dead worker so they get pruned.
Schedule::command('exports:recover-stuck')->hourly()->withoutOverlapping();

// Reclaim stored file bytes on disk with no ownership record (leaked/aborted
// uploads). The client reconciles manifest-unreferenced blobs on its own.
Schedule::command('files:sweep-orphans')->daily()->withoutOverlapping();

// Delete expired public note-share snapshots so their plaintext content does not
// linger past its expiry (these anonymous rows can't be targeted at erasure).
Schedule::command('shares:prune')->daily()->withoutOverlapping();
