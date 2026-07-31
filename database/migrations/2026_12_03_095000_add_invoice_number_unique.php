<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GoBD integrity: an assigned invoice number must be unique per user per year.
 * The relational migration only had a plain index, so the app-level finalize
 * lock had a phantom-insert race. Enforce it in the DB with a PARTIAL unique
 * index (only over numbered invoices — drafts have number = NULL and must not
 * collide). Portable across pgsql + sqlite (both support partial indexes).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX invoices_number_unique ON invoices (user_id, year, number) WHERE number IS NOT NULL AND deleted_at IS NULL'
            );
        } else {
            Schema::table('invoices', function ($table): void {
                $table->unique(['user_id', 'year', 'number'], 'invoices_number_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS invoices_number_unique');
        } else {
            Schema::table('invoices', function ($table): void {
                $table->dropUnique('invoices_number_unique');
            });
        }
    }
};
