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

// Fire calendar event alarms (VALARM) that have come due.
Schedule::command('calendar:remind')->everyMinute()->withoutOverlapping();

// Refresh subscribed remote ICS feeds that are due (each feed checks its own
// interval; runs every 15 minutes).
Schedule::command('calendar:refresh-subscriptions')->everyFifteenMinutes()->withoutOverlapping();

// Rebuild the holidays calendar daily (advances the rolling year window).
Schedule::command('calendar:refresh-holidays')->daily()->withoutOverlapping();

// Trim the WebDAV sync-collection change logs (bounded history per collection).
Schedule::command('dav:prune-changes')->daily()->withoutOverlapping();

// Pull every mail account into the local archive (server-deleted mail is kept).
Schedule::command('mail:sync')->hourly()->withoutOverlapping();

// Remove expired download exports (past their retention window) and their zips.
Schedule::command('exports:prune')->daily()->withoutOverlapping();

// Fail exports left stuck building by a dead worker so they get pruned.
Schedule::command('exports:recover-stuck')->hourly()->withoutOverlapping();

// Permanently purge files trashed longer than the retention window (and blobs).
Schedule::command('files:prune-trash')->daily()->withoutOverlapping();
