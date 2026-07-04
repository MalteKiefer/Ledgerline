<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar display + behaviour preferences on the global settings row: week
 * start (Monday/Sunday), whether to show ISO week numbers, and the default
 * duration for a new event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('calendar_week_start', 10)->default('monday');
            $table->boolean('calendar_week_numbers')->default(false);
            $table->unsignedInteger('calendar_default_event_minutes')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['calendar_week_start', 'calendar_week_numbers', 'calendar_default_event_minutes']);
        });
    }
};
