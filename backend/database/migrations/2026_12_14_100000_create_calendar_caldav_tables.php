<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar + CalDAV storage (Phase 1). Mirrors the contacts/CardDAV module:
 * calendars are CalDAV collections; each event stores the raw VCALENDAR/VEVENT
 * (iCalendar) as the source of truth plus denormalised columns for the UI, range
 * query and search; calendar_changes is the RFC 6578 sync-collection change log
 * sabre needs for incremental sync.
 *
 * There is NO separate DAV credential table: CalDAV clients authenticate with the
 * single app-specific `users.webdav_password` (the same one that mounts the Files
 * WebDAV tree and CardDAV). Events hard-delete (no SoftDeletes) so CalDAV
 * tombstones are logged in calendar_changes; identity + change tracking use
 * uid/etag/synctoken, not an optimistic version column. Datetimes are stored UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('uri');
            $table->string('color', 9)->nullable();          // #RRGGBB or #RRGGBBAA (Apple CALENDAR-COLOR)
            $table->string('description')->nullable();
            $table->string('timezone', 64)->default('UTC');   // default VTIMEZONE for the collection
            $table->unsignedBigInteger('synctoken')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'uri']);
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');                             // "<uuid>.ics"
            $table->string('etag', 64);                        // md5(ics)
            $table->string('uid')->nullable();                 // VEVENT UID (identity across clients)
            $table->string('component', 16)->default('VEVENT'); // VEVENT (VTODO/VJOURNAL future)
            $table->longText('ics');                           // raw VCALENDAR — SOURCE OF TRUTH
            // Denormalised for list/range-query/search only; the ICS is authoritative.
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestampTz('dtstart')->nullable();        // stored UTC
            $table->timestampTz('dtend')->nullable();          // stored UTC (exclusive end)
            $table->boolean('all_day')->default(false);
            $table->string('rrule')->nullable();               // raw RRULE (null = single occurrence)
            $table->timestampTz('recurrence_until')->nullable(); // UNTIL / computed horizon for range prefilter
            $table->string('status', 16)->nullable();          // CONFIRMED | TENTATIVE | CANCELLED
            $table->unsignedInteger('sequence')->default(0);   // VEVENT SEQUENCE
            $table->timestamps();
            $table->unique(['calendar_id', 'uri']);
            // One master VEVENT per uid per calendar. Detached recurrence overrides
            // (RECURRENCE-ID) are out of Phase-1 scope; a single row holds the full
            // VEVENT including inline overrides.
            $table->unique(['calendar_id', 'uid']);
            $table->index(['calendar_id', 'dtstart']);         // range query prefilter
            $table->index(['calendar_id', 'dtstart', 'dtend']);
        });

        // RFC 6578 sync-collection change log for calendars (analogue of dav_changes).
        Schema::create('calendar_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('operation'); // 1=added, 2=modified, 3=deleted
            $table->unsignedBigInteger('synctoken');
            $table->index(['calendar_id', 'synctoken']);
        });

        // Per-user calendar UI prefs (mirror contact_sort/contact_display_format).
        Schema::table('user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_settings', 'calendar_default_view')) {
                $table->string('calendar_default_view', 16)->default('month'); // month | week | agenda
            }
            if (! Schema::hasColumn('user_settings', 'calendar_week_start')) {
                $table->unsignedTinyInteger('calendar_week_start')->default(1); // 0=Sun, 1=Mon
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('user_settings', 'calendar_default_view')) {
                $table->dropColumn(['calendar_default_view', 'calendar_week_start']);
            }
        });
        Schema::dropIfExists('calendar_changes');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendars');
    }
};
