<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar preferences became per-user (user_settings) in the multi-user split,
 * and the first user already inherited the previous global values in
 * create_user_settings. The columns on the global app_settings row are now dead
 * — drop them. down() restores the columns (values are not recoverable, so the
 * defaults apply) for a clean rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'calendar_week_start',
                'calendar_week_numbers',
                'calendar_default_event_minutes',
                'calendar_birthdays_enabled',
                'calendar_anniversaries_enabled',
                'calendar_holiday_countries',
                'calendar_timezone',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('calendar_week_start', 10)->default('monday');
            $table->boolean('calendar_week_numbers')->default(false);
            $table->unsignedInteger('calendar_default_event_minutes')->default(60);
            $table->boolean('calendar_birthdays_enabled')->default(false);
            $table->boolean('calendar_anniversaries_enabled')->default(false);
            $table->json('calendar_holiday_countries')->nullable();
            $table->string('calendar_timezone', 64)->nullable();
        });
    }
};
