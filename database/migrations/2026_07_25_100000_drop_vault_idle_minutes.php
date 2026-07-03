<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop vault_idle_minutes: it configured the removed encryption vault's idle
 * lock. The background mail-sync interval no longer depends on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn('vault_idle_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vault_idle_minutes')->default(10)->after('gallery_geocode_grid_km');
        });
    }
};
