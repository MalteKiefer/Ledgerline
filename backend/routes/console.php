<?php

declare(strict_types=1);

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

// Recurring invoice templates: claim every due occurrence (bounded, so one
// huge backlog cannot starve other owners) and sweep every in-flight run so
// a crash or a still-pending async mail step keeps making progress. Runs
// every minute; `withoutOverlapping` plus `onOneServer` keep a slow tick from
// double-claiming across app instances.
Schedule::command('finance:run-recurring-invoices')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Ask each finance user for a current bank-statement CSV no more than once every
// seven days; a newly imported transaction batch automatically suppresses it.
Schedule::command('finance:remind-bank-csv')->dailyAt('08:10')->withoutOverlapping();

// Notify about tasks due today / overdue (throttled per task per due-date).
Schedule::command('tasks:remind')->dailyAt('07:00')->withoutOverlapping();

// Deadlines hiding in document text: read them at night (OCR and indexing are
// worker jobs, so a scan on upload would read an empty column half the time),
// remind in the morning next to the task reminder.
Schedule::command('deadlines:scan')->dailyAt('03:40')->withoutOverlapping();
Schedule::command('deadlines:remind')->dailyAt('07:05')->withoutOverlapping();

// Fire event reminders (VALARM) as their trigger time arrives (short cadence).
Schedule::command('calendar:remind')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tasks:remind-alarms')->everyFiveMinutes()->withoutOverlapping();

// Notify about contacts whose birthday is today (throttled once per contact/year).
Schedule::command('contacts:birthday-remind')->dailyAt('07:00')->withoutOverlapping();
// Ledgerline is the contact source of truth; replicas pull, version and then
// converge to the canonical local cards every five minutes.
Schedule::command('contacts:sync-sources')->everyFiveMinutes()->withoutOverlapping();

// Poll every monitored server over SSH. Agentless, so this IS the freshness of
// the data the UI shows; a user can always force a refresh on top. Every five
// minutes rather than fifteen because each run is also a data point: CPU,
// memory, load and disk are stored per snapshot, and a series sampled four
// times an hour is a shape, not a history.
// Ticks at the tightest interval a server may ask for; the command itself
// only queues the servers whose own poll_interval_s has elapsed.
Schedule::command('servers:poll')->everyThirtySeconds()->withoutOverlapping();

// Ping every server and connect to its monitored ports. Runs far more often
// than the SSH poll because it costs a socket rather than a session — an outage
// lasting minutes should not stay invisible until the next snapshot.
Schedule::command('servers:check')->everyFiveMinutes()->withoutOverlapping();

// Enforce retention on the per-server snapshot history (trend charts only).
Schedule::command('servers:prune-facts')->dailyAt('00:35')->withoutOverlapping();

// Reachability history grows continuously; the window is what bounds it.
Schedule::command('servers:prune-checks')->dailyAt('00:40')->withoutOverlapping();

// Trashed files keep occupying the quota until something removes them.
// A no-op unless files.trash_retention_days is set.
Schedule::command('files:prune-trash')->dailyAt('00:45')->withoutOverlapping();

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
// A restore drill, weekly rather than daily: it costs more than the integrity
// check next to it, and it answers a different question — not "is the archive
// readable" but "can this actually be restored".
Schedule::command('backup:drill')->weeklyOn(0, '05:00')->withoutOverlapping();

// Actually replay the latest backup into a throwaway target and re-hash a random
// sample of mirrored blobs against the live copies. Weekly rather than daily:
// unlike the integrity verification above it downloads and rehashes real data,
// so it costs far more — but it is the only check that proves a restore works
// instead of proving the archive is readable.

// Mail archive: dispatch a pull-only IMAP sync for every enabled account that is
// due (each account's own interval decides due-ness); reclaim orphaned mail
// blobs daily; prune the diagnostic log.
Schedule::command('mail:sync-accounts')->everyMinute()->withoutOverlapping();
Schedule::command('mail:sweep-orphans')->daily()->withoutOverlapping();
Schedule::command('mail-logs:prune')->dailyAt('00:35')->withoutOverlapping();
