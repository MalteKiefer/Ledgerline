<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore payment-method fields the pivot rewrite dropped (fidelity): the
 * account/card `holder` and a free-text `note`. Plaintext like the rest of the
 * payment-method row (encryption removed in v1.516.0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->text('holder')->nullable()->after('name');
            $table->text('note')->nullable()->after('paypal_email');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropColumn(['holder', 'note']);
        });
    }
};
