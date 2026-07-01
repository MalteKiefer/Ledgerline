<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the company_profiles table.
 *
 * A single, global company record: the sender identity used on invoices. Every
 * field is optional except the name; only filled fields are displayed. Invoice
 * defaults (language, currency, tax rate, number prefix and start number) live
 * here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('vat_id')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('register_court')->nullable();
            $table->string('register_number')->nullable();
            $table->string('managing_director')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('small_business')->default(false);
            $table->string('default_language', 5)->default('de');
            $table->string('default_currency', 3)->default('EUR');
            $table->unsignedSmallInteger('default_tax_rate')->default(19);
            $table->string('invoice_number_prefix')->default('RE');
            $table->unsignedInteger('invoice_number_next')->default(1);
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->text('invoice_footer_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
