<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often a server's usage snapshot is taken, per server.
 *
 * The sweep used to run on one fixed five-minute cadence for the whole fleet. A
 * machine being worked on wants a tighter view; a sleepy one does not need to
 * be woken every five minutes. Nullable = follow the default (300s), so nothing
 * changes for an existing server until its owner says otherwise.
 *
 * Bounds live in the request rules (30s..1800s): below half a minute this stops
 * being monitoring and becomes a self-inflicted load generator, and beyond half
 * an hour the history is too coarse to read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servers', 'poll_interval_s')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('poll_interval_s')->nullable()->after('temp_alert_c');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('servers', 'poll_interval_s')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('poll_interval_s');
        });
    }
};
