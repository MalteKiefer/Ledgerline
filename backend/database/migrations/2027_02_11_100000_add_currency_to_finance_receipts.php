<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recogniser already extracts a document's currency (`extractCurrency`, e.g.
 * a Backblaze/AWS receipt billed in USD) but the field was discarded — the owner
 * asked for it explicitly (Candis-parity gap). Additive + nullable; a receipt
 * without a recognised currency has none (the amount/statistics stay EUR-implicit,
 * as before — this is a display/reference field, not a conversion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->string('currency', 8)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
