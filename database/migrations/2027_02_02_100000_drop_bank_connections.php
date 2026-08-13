<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the GoCardless / PSD2 bank-retrieval feature (reverted per owner
 * decision). Drops the deployed bank_connections table and the workspace
 * credential columns on app_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_connections');
        Schema::table('app_settings', function (Blueprint $table): void {
            foreach (['gocardless_secret_id', 'gocardless_secret_key'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // One-way removal; the feature is gone. (Re-add via its original migration.)
    }
};
