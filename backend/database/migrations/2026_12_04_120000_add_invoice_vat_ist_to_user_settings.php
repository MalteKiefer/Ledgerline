<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            // VAT scheme for the Umsatzsteuer-Voranmeldung: true = Ist-Versteuerung
            // (cash-basis, VAT due on payment); false = Soll (accrual, VAT on issue).
            $table->boolean('invoice_vat_ist')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('invoice_vat_ist');
        });
    }
};
