<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `finance_time_entries.invoiced_invoice_id` carries a hard foreign key to
 * the legacy `invoices` table (see 2027_03_03_100000_create_project_planning.php).
 * The cutover in this plan (Task 17) moves `FinanceProjectPlanController::
 * invoiceTime` onto the finance-v2 invoice module, so a newly billed entry
 * can no longer be recorded there — that FK would reject a
 * `finance_invoices.id`.
 *
 * Same fix as 2027_03_04_160000_add_finance_invoice_target_to_finance_quotes.php:
 * a second, purely additive column that means exactly what its name says.
 * `FinanceTimeEntry::isBillable()` and every "already invoiced" check treat
 * either column being set as invoiced — the model doc comment's invariant
 * ("set once and never cleared... an entry that has been billed stops being
 * available") holds across both columns, not just one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_time_entries', function (Blueprint $table): void {
            $table->foreignId('invoiced_finance_invoice_id')->nullable()->after('invoiced_invoice_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            return; // sqlite: FK add would require a table rebuild; skip (test DB).
        }
        Schema::table('finance_time_entries', function (Blueprint $table): void {
            $table->foreign('invoiced_finance_invoice_id')->references('id')->on('finance_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('finance_time_entries', function (Blueprint $table): void {
                $table->dropForeign(['invoiced_finance_invoice_id']);
            });
        }
        Schema::table('finance_time_entries', function (Blueprint $table): void {
            $table->dropColumn('invoiced_finance_invoice_id');
        });
    }
};
