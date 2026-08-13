<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long the browser keeps the encryption vault unlocked while idle before
 * requiring the passphrase again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vault_idle_minutes')->default(10)->after('gallery_geocode_grid_km');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn('vault_idle_minutes');
        });
    }
};
