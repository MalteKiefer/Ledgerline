<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoicing-lifecycle slice B (additive): credit notes (Storno / Gutschrift),
 * a global invoice-level discount (Rabatt) and early-payment Skonto terms.
 *
 * - type: the document kind (invoice|credit_note). A credit note reverses a
 *   finalized invoice; it gets its own GoBD number.
 * - cancels_invoice_id: the invoice a credit note cancels (server-set, self-FK,
 *   nullOnDelete). Not fillable — only the Storno action writes it via forceFill.
 * - discount_type + discount_value: a single global discount on the net taxable
 *   base (percent|amount).
 * - skonto_percent + skonto_days: early-payment terms printed on the invoice.
 *
 * All columns are nullable/defaulted and additive; no existing invoice column is
 * touched, so the live invoices stay untouched. The self-referencing foreign key
 * is only added on drivers that support ALTER ADD FOREIGN KEY (Postgres in prod);
 * SQLite (tests) enforces the relation at the application layer (server-set only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('type', 16)->default('invoice');       // invoice|credit_note
            $table->unsignedBigInteger('cancels_invoice_id')->nullable();
            $table->string('discount_type', 8)->nullable();       // percent|amount
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('skonto_percent', 5, 2)->nullable();
            $table->unsignedInteger('skonto_days')->nullable();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreign('cancels_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropForeign(['cancels_invoice_id']);
            });
        }
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['type', 'cancels_invoice_id', 'discount_type', 'discount_value', 'skonto_percent', 'skonto_days']);
        });
    }
};
