<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company profile used to render invoices. This is the workspace owner's own
 * business identity (it prints on every invoice), so it is stored in the clear
 * — the zero-knowledge posture protects customer/invoice data, which lives
 * client-side in the sealed /store manifest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('gallery_geocode_interval_ms');
            $table->text('company_address')->nullable()->after('company_name');
            $table->string('company_email')->nullable()->after('company_address');
            $table->string('company_phone')->nullable()->after('company_email');
            $table->string('company_tax_id')->nullable()->after('company_phone');
            $table->string('company_vat_id')->nullable()->after('company_tax_id');
            $table->string('company_iban')->nullable()->after('company_vat_id');
            $table->string('company_bic')->nullable()->after('company_iban');
            $table->string('company_bank_name')->nullable()->after('company_bic');
            $table->string('company_logo_path')->nullable()->after('company_bank_name');
            $table->string('invoice_number_prefix')->nullable()->after('company_logo_path');
            $table->unsignedSmallInteger('invoice_number_padding')->nullable()->after('invoice_number_prefix');
            $table->decimal('invoice_default_vat_rate', 5, 2)->nullable()->after('invoice_number_padding');
            $table->unsignedSmallInteger('invoice_payment_terms_days')->nullable()->after('invoice_default_vat_rate');
            $table->text('invoice_footer_text')->nullable()->after('invoice_payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'company_name', 'company_address', 'company_email', 'company_phone',
                'company_tax_id', 'company_vat_id', 'company_iban', 'company_bic',
                'company_bank_name', 'company_logo_path', 'invoice_number_prefix',
                'invoice_number_padding', 'invoice_default_vat_rate',
                'invoice_payment_terms_days', 'invoice_footer_text',
            ]);
        });
    }
};
