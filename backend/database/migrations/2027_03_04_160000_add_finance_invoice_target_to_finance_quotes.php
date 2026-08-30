<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `finance_quotes.converted_invoice_id` carries a hard foreign key to the
 * legacy `invoices` table (see 2027_03_01_100000_create_finance_quotes.php).
 * The cutover in this plan (Task 17) moves quote-to-invoice conversion onto
 * the finance-v2 invoice module, so a newly converted quote can no longer be
 * recorded there — that FK would reject a `finance_invoices.id`.
 *
 * Rather than fight a foreign key across two different target tables, this
 * adds a second, purely additive column that means exactly what its name
 * says: the finance-v2 invoice this quote became, if any. A quote converted
 * before this migration keeps pointing at its legacy invoice through the old
 * column forever; every quote converted after this migration populates the
 * new one instead. `FinanceQuoteController::convertToInvoice` reads whichever
 * one is set. sqlite (used for the local/test database) cannot add a foreign
 * key without a full table rebuild, so — matching the existing precedent in
 * 2026_12_12_100000_add_partner_fk_to_finance_receipts.php — the constraint
 * is added on every driver except sqlite; ownership is enforced by the
 * application regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_quotes', function (Blueprint $table): void {
            $table->foreignId('converted_finance_invoice_id')->nullable()->after('converted_invoice_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            return; // sqlite: FK add would require a table rebuild; skip (test DB).
        }
        Schema::table('finance_quotes', function (Blueprint $table): void {
            $table->foreign('converted_finance_invoice_id')->references('id')->on('finance_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('finance_quotes', function (Blueprint $table): void {
                $table->dropForeign(['converted_finance_invoice_id']);
            });
        }
        Schema::table('finance_quotes', function (Blueprint $table): void {
            $table->dropColumn('converted_finance_invoice_id');
        });
    }
};
