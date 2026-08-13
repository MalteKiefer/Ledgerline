<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §19 Kleinunternehmer flag on the per-user settings row. When true, the user
 * invoices without VAT (keine Umsatzsteuer ausgewiesen) and the VAT return
 * reports zero output VAT. Additive + nullable-safe (boolean default false) —
 * no existing finance column is altered, no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('small_business')->default(false)->after('invoice_default_vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('small_business');
        });
    }
};
