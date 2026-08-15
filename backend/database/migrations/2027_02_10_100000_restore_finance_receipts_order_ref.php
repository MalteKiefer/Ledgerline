<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `finance_receipts.order_ref` was declared in 2027_02_09_100000 (recorded as
 * applied) but is missing from the live production schema — a drift discovered
 * while diagnosing the order-ref-based receipt matcher, which reads the column via
 * the Eloquent model (silently null on a missing column, not an error) and had
 * therefore never actually grouped a single split-order receipt in production.
 * `Schema::hasColumn`-guarded so it is a safe no-op if the column does exist
 * somewhere it wasn't found. Additive + nullable — no data loss either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('finance_receipts', 'order_ref')) {
            return;
        }
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->string('order_ref', 64)->nullable()->after('date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('finance_receipts', 'order_ref')) {
            return;
        }
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->dropColumn('order_ref');
        });
    }
};
