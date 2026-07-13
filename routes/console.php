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

// Reclaim stored file/gallery bytes on disk with no ownership record (leaked/
// aborted uploads, or bytes orphaned by an interrupted erasure). The client
// reconciles manifest-unreferenced blobs on its own; this is the crash net.
Schedule::command('files:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('gallery:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('contacts:sweep-orphans')->daily()->withoutOverlapping();

// Delete expired public note-share snapshots so their plaintext content does not
// linger past its expiry (these anonymous rows can't be targeted at erasure).
Schedule::command('shares:prune')->daily()->withoutOverlapping();

// Drop expired/consumed QR device-pairing rows (short-lived, single-use).
Schedule::command('device-pairings:prune')->hourly()->withoutOverlapping();

// Revoke idle + wipe-flagged device bearer tokens (idle + offline-wipe backstop).
Schedule::command('devices:prune-tokens')->daily()->withoutOverlapping();

// Alert the configured channels about new recorded server errors.
Schedule::command('ops:alert-errors')->hourly()->withoutOverlapping();

// Record a daily per-module storage snapshot for the System page trend.
Schedule::command('ops:snapshot-storage')->dailyAt('00:10')->withoutOverlapping();

// Enforce retention on the append-only security audit log.
Schedule::command('audit:prune')->dailyAt('00:20')->withoutOverlapping();

// Verify the latest successful backup restores, and alert on staleness/failure.
Schedule::command('backups:verify')->dailyAt('04:30')->withoutOverlapping();
