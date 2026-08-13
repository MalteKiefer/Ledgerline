<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable cap on the number of paired mobile devices (Sanctum
 * tokens) a user may hold. Null falls back to config('devices.max').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_connected_devices')->nullable()->after('vault_public_idle_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn('max_connected_devices');
        });
    }
};
