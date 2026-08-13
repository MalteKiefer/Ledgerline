<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable vault lock policy: how many days a trusted device stays
 * unlocked across browser restarts, and the idle timeout (minutes) applied when
 * a user unlocks on a public computer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vault_remember_days')->nullable()->after('gallery_geocode_grid_km');
            $table->unsignedSmallInteger('vault_public_idle_minutes')->nullable()->after('vault_remember_days');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['vault_remember_days', 'vault_public_idle_minutes']);
        });
    }
};
