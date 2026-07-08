<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the Calendar, Contacts and DAV (CalDAV/CardDAV) modules entirely. Drops
 * their tables (children first for the FKs), the gallery people.contact_id link,
 * and any dangling resource/public shares that pointed at a calendar or address
 * book. Gallery/Files/Notes/Bookmarks/Todos are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dangling shares that referenced now-removed models.
        foreach (['resource_shares', 'public_shares'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->whereIn('shareable_type', [
                    'App\\Models\\Calendar', 'App\\Models\\AddressBook', 'App\\Models\\CalendarObject', 'App\\Models\\Contact',
                ])->delete();
            }
        }

        // Gallery no longer links a person to a contact. Drop the index before the
        // column (SQLite errors otherwise, which would break the whole test suite).
        if (Schema::hasColumn('people', 'contact_id')) {
            Schema::table('people', function (Blueprint $table): void {
                $table->dropIndex('people_contact_id_index');
                $table->dropColumn('contact_id');
            });
        }

        // Drop with FK enforcement off so inter-table constraints don't dictate
        // order (portable across pgsql + sqlite).
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach ([
                'contact_emails', 'contact_phones', 'contact_group', 'contact_duplicate_dismissals',
                'calendar_alarm_log', 'calendar_changes', 'dav_changes', 'calendar_objects',
                'contacts', 'contact_groups', 'calendars', 'address_books', 'dav_credentials',
            ] as $table) {
                Schema::dropIfExists($table);
            }
        });
    }

    public function down(): void
    {
        // One-way removal; the old create migrations remain in history for a full
        // rebuild-from-zero, but there is no rollback path for this drop.
    }
};
