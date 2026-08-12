<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct bank account retrieval via GoCardless Bank Account Data (ex-Nordigen,
 * PSD2/XS2A). The workspace-level API credentials live encrypted on app_settings;
 * each per-user bank connection tracks its consent (requisition) + linked account
 * and feeds the existing bank_transactions table (sig-deduplicated).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->text('gocardless_secret_id')->nullable();   // encrypted cast
            $table->text('gocardless_secret_key')->nullable();  // encrypted cast
        });

        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The finance payment-method (account) that receives the pulled txns.
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('gocardless');
            $table->string('institution_id', 128);
            $table->string('institution_name', 191)->nullable();
            $table->string('requisition_id', 128)->nullable();
            $table->string('reference', 64)->unique();   // our consent correlation ref
            $table->string('account_id', 128)->nullable(); // chosen GoCardless account id
            $table->string('status', 24)->default('created'); // created|linked|expired|error
            $table->timestamp('consent_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['gocardless_secret_id', 'gocardless_secret_key']);
        });
    }
};
