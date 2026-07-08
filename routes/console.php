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

// Fire to-do reminders that have come due.
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();

// Pull every mail account into the local archive (server-deleted mail is kept).

// Remove expired download exports (past their retention window) and their zips.
Schedule::command('exports:prune')->daily()->withoutOverlapping();

// Fail exports left stuck building by a dead worker so they get pruned.
Schedule::command('exports:recover-stuck')->hourly()->withoutOverlapping();

// Permanently purge files trashed longer than the retention window (and blobs).
Schedule::command('files:prune-trash')->daily()->withoutOverlapping();

// Same for gallery photos: purge trashed past retention + sweep orphan blobs.
Schedule::command('gallery:prune-trash')->daily()->withoutOverlapping();

// Delete expired public note-share snapshots so their plaintext content does not
// linger past its expiry (these anonymous rows can't be targeted at erasure).
Schedule::command('shares:prune')->daily()->withoutOverlapping();
