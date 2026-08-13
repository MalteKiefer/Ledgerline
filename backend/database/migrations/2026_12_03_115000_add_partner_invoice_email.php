<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business partners gain an optional dedicated invoice email (Rechnungs-E-Mail),
 * separate from the general `email`. Invoices are sent to it when set; otherwise
 * they fall back to the general email. Additive + nullable only — no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_partners', function (Blueprint $table): void {
            $table->string('invoice_email', 320)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('finance_partners', function (Blueprint $table): void {
            $table->dropColumn('invoice_email');
        });
    }
};
