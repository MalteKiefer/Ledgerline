<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track alert delivery per error signature instead of via a single cache cursor.
 * A cache flush (Valkey restart) must not silently drop the alert window, and
 * the marker must only advance after a row has actually been alerted on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_events', function (Blueprint $table): void {
            $table->timestamp('alerted_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('error_events', function (Blueprint $table): void {
            $table->dropColumn('alerted_at');
        });
    }
};
