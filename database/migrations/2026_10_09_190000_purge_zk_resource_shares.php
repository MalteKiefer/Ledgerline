<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purge stale cross-user shares of zero-knowledge resources. Sharing notes/files/
 * folders was disabled (v1.361) because a sharee can't decrypt them and a write
 * sharee would lock the owner out — but pre-existing resource_shares/public_shares
 * rows still made another user's ciphertext surface via the ownerOrShared read
 * scope. Delete them (defense in depth alongside the scope now excluding
 * is_encrypted rows + the ALLOWED lists that already reject new ones).
 */
return new class extends Migration
{
    public function up(): void
    {
        $zk = ['App\\Models\\Note', 'App\\Models\\StoredFile', 'App\\Models\\FileFolder'];
        foreach (['resource_shares', 'public_shares'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->whereIn('shareable_type', $zk)->delete();
            }
        }
    }

    public function down(): void
    {
        // One-way cleanup of dead share rows.
    }
};
