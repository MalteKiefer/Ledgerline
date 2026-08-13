<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-removal cleanup of dead residue left behind by the Calendar/Contacts/DAV
 * teardown (2026_10_09_160000). Drops the orphaned per-user calendar/contact
 * preference columns from user_settings and the now-unused WebDAV `locks` table.
 * One-way cleanup: there is no rollback for this removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'calendar_week_start',
            'calendar_week_numbers',
            'calendar_default_event_minutes',
            'calendar_timezone',
            'calendar_birthdays_enabled',
            'calendar_anniversaries_enabled',
            'calendar_holiday_countries',
            'contact_sort',
            'contact_display_format',
        ];

        $present = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('user_settings', $column),
        ));

        if ($present !== []) {
            Schema::table('user_settings', function (Blueprint $table) use ($present): void {
                $table->dropColumn($present);
            });
        }

        // Orphaned WebDAV lock storage — DAV was removed with the modules.
        Schema::dropIfExists('locks');
    }

    public function down(): void
    {
        // One-way cleanup; the removed columns and lock table have no rollback path.
    }
};
