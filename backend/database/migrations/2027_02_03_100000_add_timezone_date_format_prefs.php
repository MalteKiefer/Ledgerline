<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user display preferences: an optional hard IANA timezone override (null =
 * follow the browser/system) and a date-format choice. Non-secret presentation,
 * like the 12/24h clock and measurement units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('time_format');
            $table->string('date_format', 16)->default('system')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'date_format']);
        });
    }
};
