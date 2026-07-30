<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): payment methods / accounts
 * (bank|card|paypal|cash|other). One row per method. type/name/business +
 * url/icon stay plaintext for listing; the sensitive account identifiers
 * (IBAN/BIC/card number/…) carry an `encrypted` cast → kept out of DB dumps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);  // bank|card|paypal|cash|other
            $table->string('name', 200);
            $table->boolean('business')->default(false);
            $table->text('url')->nullable();
            $table->text('icon')->nullable();
            // Encrypted account identifiers:
            $table->text('iban')->nullable();
            $table->text('bic')->nullable();
            $table->text('bank')->nullable();
            $table->text('account_no')->nullable();
            $table->text('card_number')->nullable();
            $table->text('card_network')->nullable();
            $table->text('card_expiry')->nullable();
            $table->text('paypal_email')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
