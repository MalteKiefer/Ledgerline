<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring to-dos + due-date reminders (additive, non-destructive).
 *
 *  - `recurrence`  (none|daily|weekly|monthly|yearly; null/none = one-off) —
 *    user-set; when a recurring task is completed, the next occurrence spawns.
 *  - `reminded_at` — server-only dedup marker so a due reminder fires once
 *    per due arrival (re-arms when the due date is moved past reminded_at).
 *
 * On PostgreSQL a functional GIN index backs the full-text search endpoint;
 * SQLite (tests) uses the portable LIKE fallback and skips the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->string('recurrence', 16)->nullable()->after('done');
            $table->timestampTz('reminded_at')->nullable()->after('recurrence');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS todos_fts_idx ON todos USING gin (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'') || ' ' || coalesce(url,'')))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS todos_fts_idx');
        }

        Schema::table('todos', function (Blueprint $table): void {
            $table->dropColumn(['recurrence', 'reminded_at']);
        });
    }
};
