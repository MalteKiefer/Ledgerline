<?php

declare(strict_types=1);

use App\Services\Calendar\CalendarEventService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the first VEVENT VALARM's lead time (minutes-before-start) into a
 * column so calendar:remind can cheaply find events with a reminder and compute
 * the trigger without parsing every ICS. Mirrors recurrence_until. Backfilled
 * from the stored ICS; kept in sync by CalendarEventService::denormalize().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->unsignedInteger('alarm_minutes_before')->nullable()->after('status');
        });

        $service = app(CalendarEventService::class);
        DB::table('calendar_events')->select('id', 'ics')->orderBy('id')->chunk(200, function ($rows) use ($service): void {
            foreach ($rows as $row) {
                $minutes = $service->parse((string) $row->ics)['alarm_minutes_before'] ?? null;
                if ($minutes !== null) {
                    DB::table('calendar_events')->where('id', $row->id)->update(['alarm_minutes_before' => (int) $minutes]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('alarm_minutes_before');
        });
    }
};
