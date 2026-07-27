<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optimistic-concurrency versions for the two sealed writes that lacked one (store
 * merge-safety spec §5/§6.3/§6.4): the personal vault key material (`vaults`, rotated
 * by VaultController::rotate) and the public/file/gallery share record (`public_shares`,
 * updated by ManagesPublicShares). A stale writer is now rejected with 409 instead of
 * blindly overwriting newer sealed data. Additive + reversible; default 0 keeps every
 * existing row valid and older clients (which omit expected_version) behave as today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaults', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0);
        });
        Schema::table('public_shares', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vaults', fn (Blueprint $table) => $table->dropColumn('version'));
        Schema::table('public_shares', fn (Blueprint $table) => $table->dropColumn('version'));
    }
};
