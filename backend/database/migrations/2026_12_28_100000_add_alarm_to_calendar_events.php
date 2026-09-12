<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the first VEVENT VALARM's lead time (minutes-before-start) into a
 * column so calendar:remind can cheaply find events with a reminder and compute
 * the trigger without parsing every ICS. Mirrors recurrence_until.
 *
 * The original backfill (parsing every stored ICS via the now-removed
 * CalendarEventService) is gone: the Calendar module and the calendar_events
 * table it populated are being dropped later in this same migration history, so
 * a fresh install never has rows to backfill anyway, and the removed service no
 * longer exists to run it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->unsignedInteger('alarm_minutes_before')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('alarm_minutes_before');
        });
    }
};
