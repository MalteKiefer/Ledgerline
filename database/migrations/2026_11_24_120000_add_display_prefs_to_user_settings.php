<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global per-user display preferences (like the interface language): measurement
 * units + clock format. Non-secret presentation choices — the actual data stays
 * zero-knowledge; only its DISPLAY unit/format is chosen here. Applied client-side
 * across web and mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('unit_distance', 8)->default('km');   // km | mi
            $table->string('unit_elevation', 8)->default('m');   // m | ft
            $table->string('unit_weight', 8)->default('kg');     // kg | lb
            $table->string('unit_temp', 8)->default('c');        // c | f
            $table->string('unit_glucose', 8)->default('mgdl');  // mgdl | mmoll
            $table->string('time_format', 8)->default('24h');    // 24h | 12h
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['unit_distance', 'unit_elevation', 'unit_weight', 'unit_temp', 'unit_glucose', 'time_format']);
        });
    }
};
