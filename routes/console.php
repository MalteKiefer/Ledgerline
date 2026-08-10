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

// Refresh EUR exchange rates once a day for the finance amount suggestions (no user data).
Schedule::command('finance:fetch-fx')->dailyAt('03:15')->withoutOverlapping();

// Remind the owner about unpaid, overdue invoices (throttled per invoice).
Schedule::command('invoices:remind')->dailyAt('08:00')->withoutOverlapping();

// Notify about tasks due today / overdue (throttled per task per due-date).
Schedule::command('tasks:remind')->dailyAt('07:00')->withoutOverlapping();

// Fire event reminders (VALARM) as their trigger time arrives (short cadence).
Schedule::command('calendar:remind')->everyFiveMinutes()->withoutOverlapping();

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

// Enforce the (shorter) retention on the high-volume device access trail.
Schedule::command('device-access-log:prune')->dailyAt('00:25')->withoutOverlapping();
Schedule::command('request-log:prune')->dailyAt('00:27')->withoutOverlapping();

// Verify the latest successful backup restores, and alert on staleness/failure.
Schedule::command('backups:verify')->dailyAt('04:30')->withoutOverlapping();

// Mail archive: dispatch a pull-only IMAP sync for every enabled account that is
// due (each account's own interval decides due-ness); reclaim orphaned mail
// blobs daily; prune the diagnostic log.
Schedule::command('mail:sync-accounts')->everyMinute()->withoutOverlapping();
Schedule::command('mail:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('mail-logs:prune')->dailyAt('00:35')->withoutOverlapping();
