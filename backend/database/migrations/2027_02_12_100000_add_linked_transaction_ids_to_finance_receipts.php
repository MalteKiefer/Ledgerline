<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt normally settles via ONE bank_transaction_id, but some vendors split a
 * single invoice across several separate charges (found on a real INWX invoice:
 * one 42.07 EUR bill, debited as two bookings of 32.55 EUR + 9.52 EUR two days
 * apart) — a plain scalar FK can't express that. Additive, nullable, and used ONLY
 * for the multi-transaction case: the well-tested single-link path via
 * bank_transaction_id is untouched. A receipt is either linked via
 * bank_transaction_id (the common case) OR via linked_transaction_ids (a split
 * payment), never both at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->json('linked_transaction_ids')->nullable()->after('bank_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->dropColumn('linked_transaction_ids');
        });
    }
};
