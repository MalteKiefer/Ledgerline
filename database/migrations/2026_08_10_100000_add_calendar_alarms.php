<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar reminders (VALARM). alarm_minutes denormalises the first VALARM's
 * lead time so the scheduler can query events cheaply; calendar_alarm_log records
 * which (object, occurrence) alarms have already fired so recurring events fire
 * once per instance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_objects', function (Blueprint $table): void {
            $table->unsignedInteger('alarm_minutes')->nullable()->after('rrule');
        });

        Schema::create('calendar_alarm_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('calendar_object_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurrence_at');
            $table->timestamp('fired_at');
            $table->unique(['calendar_object_id', 'occurrence_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_alarm_log');
        Schema::table('calendar_objects', function (Blueprint $table): void {
            $table->dropColumn('alarm_minutes');
        });
    }
};
