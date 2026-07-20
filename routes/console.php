<?php

use App\Models\PublicShare;
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

// Reclaim stored file/gallery bytes on disk with no ownership record (leaked/
// aborted uploads, or bytes orphaned by an interrupted erasure). The client
// reconciles manifest-unreferenced blobs on its own; this is the crash net.
Schedule::command('files:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('gallery:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('contacts:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('shared-folders:sweep-orphans')->daily()->withoutOverlapping();

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

// Drop expired public share links (the sealed manifest + blob allow-list) so an
// expired link stops resolving and stops pinning its rows. The blobs themselves
// stay owned by the user; only the share record is removed.
Schedule::call(function (): void {
    PublicShare::whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
})->hourly()->name('prune-expired-shares')->withoutOverlapping();
