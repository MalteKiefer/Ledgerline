<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * finance_receipts.partner_id was a bare unsignedBigInteger with no foreign key,
 * unlike invoices.partner_id / bank_transactions.* which are all constrained. Add
 * the missing FK with nullOnDelete so a hard-deleted partner can't leave a
 * dangling pointer. Additive + driver-guarded (sqlite can't ALTER-ADD a FK
 * in-place; the app enforces ownership regardless).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // sqlite: FK add would require a table rebuild; skip (test DB).
        }
        // Null any orphaned pointers first so the constraint can be added cleanly.
        DB::statement('UPDATE finance_receipts SET partner_id = NULL WHERE partner_id IS NOT NULL AND partner_id NOT IN (SELECT id FROM finance_partners)');

        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->foreign('partner_id')->references('id')->on('finance_partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->dropForeign(['partner_id']);
        });
    }
};
