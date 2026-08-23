<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave room on the page so a flag change can stay heap-only.
 *
 * An archived message carries its search text, so a row averages 11 KB and a
 * second version of it rarely fits beside the first. Without that room Postgres
 * cannot do a heap-only update, and every change to `seen` -- a flag -- rewrites
 * all seven indexes, including a 42 MB GIN. Measured on the live table: 43 of
 * 598 updates were heap-only, and marking fifty messages read took 5.4 seconds.
 *
 * Fillfactor applies as pages are written, so this improves gradually rather
 * than at once; a VACUUM FULL would apply it immediately but takes an exclusive
 * lock, which is not worth it for a table this size.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('mail_messages')) {
            return;
        }

        DB::statement('ALTER TABLE mail_messages SET (fillfactor = 70)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('mail_messages')) {
            return;
        }

        DB::statement('ALTER TABLE mail_messages RESET (fillfactor)');
    }
};
