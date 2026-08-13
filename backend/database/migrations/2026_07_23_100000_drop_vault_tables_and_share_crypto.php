<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tear down the last remnants of the removed zero-knowledge vault: the vault /
 * vault_manifests tables, and the client-encryption columns on note_shares
 * (shares are now a server-rendered plaintext snapshot in `content`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vault_manifests');
        Schema::dropIfExists('vault');

        Schema::table('note_shares', function (Blueprint $table): void {
            $table->dropColumn([
                'cipher', 'nonce', 'wrapped_key',
                'wrap_salt', 'wrap_nonce', 'wrap_ops', 'wrap_mem',
            ]);
        });
    }

    public function down(): void
    {
        // The vault tables are not recreated (the feature is gone); only the
        // note_shares columns are restored, as nullable, for reversibility.
        Schema::table('note_shares', function (Blueprint $table): void {
            $table->longText('cipher')->nullable();
            $table->string('nonce')->nullable();
            $table->longText('wrapped_key')->nullable();
            $table->string('wrap_salt')->nullable();
            $table->string('wrap_nonce')->nullable();
            $table->unsignedInteger('wrap_ops')->nullable();
            $table->unsignedBigInteger('wrap_mem')->nullable();
        });
    }
};
