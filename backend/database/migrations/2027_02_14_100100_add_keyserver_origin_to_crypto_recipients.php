<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks where a saved recipient's key came FROM — a manually pasted key has
 * no origin server (null); one imported via keyserver search records the
 * server + the exact key id/fingerprint it was fetched by, so it can later be
 * refreshed (re-fetched from the same server, e.g. after a revocation or a
 * new subkey/uid). Additive + nullable, no data loss on existing recipients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_recipients', function (Blueprint $table): void {
            $table->foreignId('key_server_id')->nullable()->after('user_id')
                ->constrained('key_servers')->nullOnDelete();
            $table->string('key_id', 64)->nullable()->after('fingerprint');
            $table->timestamp('refreshed_at')->nullable()->after('cert_pem');
        });
    }

    public function down(): void
    {
        Schema::table('crypto_recipients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('key_server_id');
            $table->dropColumn(['key_id', 'refreshed_at']);
        });
    }
};
