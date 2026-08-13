<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device state for CLI clients: a remote-wipe kill switch and a lightweight
 * sync-activity heartbeat, both owned per paired token. The heartbeat lets the
 * web show whether a client is currently syncing; the wipe flag tells a client
 * to erase its local state on next contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->timestamp('wipe_requested_at')->nullable()->after('ip');
            $table->string('sync_state', 16)->nullable()->after('wipe_requested_at');
            $table->string('sync_detail', 160)->nullable()->after('sync_state');
            $table->timestamp('sync_reported_at')->nullable()->after('sync_detail');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['wipe_requested_at', 'sync_state', 'sync_detail', 'sync_reported_at']);
        });
    }
};
