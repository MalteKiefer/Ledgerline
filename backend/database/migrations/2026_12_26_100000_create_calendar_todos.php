<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CalDAV task (VTODO) storage. Mirrors the calendar-events + CardDAV pattern: the
 * raw VCALENDAR/VTODO (iCalendar) is the source of truth; the other columns are
 * denormalised for list/filter/search. Identity + change tracking use uid / etag /
 * synctoken (DAV-native, no optimistic version column); todos hard-delete and log a
 * tombstone into calendar_todo_changes for the RFC 6578 sync-collection REPORT.
 *
 * A calendar declares its component type via the new `calendars.component` column
 * (VEVENT | VTODO). A VTODO calendar is a task list (Apple Reminders / Tasks.org);
 * its CalDAV collection advertises supported-calendar-component-set=[VTODO] and its
 * objects live in this table. Datetimes are stored UTC. There is NO separate DAV
 * credential: CalDAV clients authenticate with the single `users.webdav_password`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A calendar's component type: VEVENT (events) or VTODO (task list). Fixed
        // at creation. Additive + guarded so it is safe to re-run.
        Schema::table('calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendars', 'component')) {
                $table->string('component', 16)->default('VEVENT')->after('kind'); // VEVENT | VTODO
            }
        });

        Schema::create('calendar_todos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // AssignsOwner owner column
            $table->string('uri');                              // "<uuid>.ics"
            $table->string('etag', 64);                         // md5(ics)
            $table->string('uid')->nullable();                  // VTODO UID (identity across clients)
            $table->longText('ics');                            // raw VCALENDAR — SOURCE OF TRUTH
            // Denormalised for list/filter/search only; the ICS is authoritative.
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('NEEDS-ACTION'); // NEEDS-ACTION | IN-PROCESS | COMPLETED | CANCELLED
            $table->unsignedTinyInteger('priority')->nullable();    // 0-9 (1-4 high, 5 medium, 6-9 low; 0/absent = none)
            $table->unsignedTinyInteger('percent_complete')->nullable(); // 0-100
            $table->timestampTz('due')->nullable();             // DUE, stored UTC
            $table->timestampTz('dtstart')->nullable();         // DTSTART, stored UTC
            $table->timestampTz('completed_at')->nullable();    // COMPLETED, stored UTC
            $table->boolean('all_day')->default(false);         // DUE/DTSTART carried VALUE=DATE
            $table->string('rrule')->nullable();                // raw RRULE (null = single occurrence)
            $table->json('categories')->nullable();             // CATEGORIES list
            $table->string('related_to')->nullable();           // parent task UID (subtasks)
            $table->unsignedInteger('sequence')->default(0);    // VTODO SEQUENCE
            $table->integer('sort_order')->default(0);          // manual ordering within a list
            $table->timestamps();
            $table->unique(['calendar_id', 'uri']);
            $table->unique(['calendar_id', 'uid']);
            $table->index(['user_id', 'calendar_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due']);
        });

        // RFC 6578 sync-collection change log for task lists (analogue of
        // calendar_changes, kept separate so the event path is untouched).
        Schema::create('calendar_todo_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('operation'); // 1=added, 2=modified, 3=deleted
            $table->unsignedBigInteger('synctoken');
            $table->index(['calendar_id', 'synctoken']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_todo_changes');
        Schema::dropIfExists('calendar_todos');
        Schema::table('calendars', function (Blueprint $table): void {
            if (Schema::hasColumn('calendars', 'component')) {
                $table->dropColumn('component');
            }
        });
    }
};
