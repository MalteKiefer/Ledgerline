<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice branding: an accent + heading colour applied to the rendered invoice,
 * plus free-text payment methods and payment-terms blocks for the footer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('invoice_accent_color', 9)->nullable()->after('invoice_footer_text');
            $table->string('invoice_heading_color', 9)->nullable()->after('invoice_accent_color');
            $table->text('invoice_payment_methods')->nullable()->after('invoice_heading_color');
            $table->text('invoice_payment_terms_text')->nullable()->after('invoice_payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['invoice_accent_color', 'invoice_heading_color', 'invoice_payment_methods', 'invoice_payment_terms_text']);
        });
    }
};
