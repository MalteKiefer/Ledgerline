<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cost project so far collected money (hand-typed ledger rows, bank
 * transactions, receipts) but no other evidence. A build project also has
 * plans, permits, quotes and site photos, and those live in the Files and
 * Gallery modules.
 *
 * Same shape as bank_transactions.finance_project_id / finance_receipts
 * .finance_project_id: one nullable pointer per row, so a file or photo belongs
 * to at most one project. Additive; nullOnDelete keeps a deleted project from
 * leaving a dangling pointer, and the row itself (the file, the photo) is never
 * touched by that.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        foreach (['files', 'gallery_photos'] as $table) {
            if (Schema::hasColumn($table, 'finance_project_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($sqlite): void {
                if ($sqlite) {
                    // sqlite cannot ALTER-ADD a foreign key in place; the column
                    // alone is enough for the test DB (ownership + existence are
                    // enforced by the request rules either way).
                    $blueprint->unsignedBigInteger('finance_project_id')->nullable();
                } else {
                    $blueprint->foreignId('finance_project_id')->nullable()->constrained('finance_projects')->nullOnDelete();
                }
            });
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->index('finance_project_id', $table.'_finance_project_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['files', 'gallery_photos'] as $table) {
            if (! Schema::hasColumn($table, 'finance_project_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_finance_project_idx');
                $blueprint->dropColumn('finance_project_id');
            });
        }
    }
};
