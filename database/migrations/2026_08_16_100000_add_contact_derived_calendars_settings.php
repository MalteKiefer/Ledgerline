<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toggles for the auto-generated calendars derived from contacts: birthdays
 * (BDAY) and anniversaries (ANNIVERSARY), materialised as read-only, yearly
 * recurring all-day events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('calendar_birthdays_enabled')->default(false);
            $table->boolean('calendar_anniversaries_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['calendar_birthdays_enabled', 'calendar_anniversaries_enabled']);
        });
    }
};
