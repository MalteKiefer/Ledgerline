<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Events may carry several reminders now, so the fire-once gate must be per
// (object, occurrence, lead) instead of per (object, occurrence).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_alarm_log', function (Blueprint $table) {
            $table->unsignedInteger('lead_minutes')->default(0)->after('occurrence_at');
            $table->dropUnique(['calendar_object_id', 'occurrence_at']);
            $table->unique(['calendar_object_id', 'occurrence_at', 'lead_minutes'], 'calendar_alarm_log_fire_once');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_alarm_log', function (Blueprint $table) {
            $table->dropUnique('calendar_alarm_log_fire_once');
            $table->unique(['calendar_object_id', 'occurrence_at']);
            $table->dropColumn('lead_minutes');
        });
    }
};
