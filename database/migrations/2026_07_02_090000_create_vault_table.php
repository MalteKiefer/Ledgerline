<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace encryption vault: a single row holding only ciphertext and
 * key-derivation parameters. The passphrase and the vault key never reach the
 * server, so nothing here can decrypt anything on its own — it lets the browser
 * re-derive and unwrap the vault key after the user enters the passphrase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault', function (Blueprint $table): void {
            $table->id();
            $table->text('salt');                          // KDF salt (base64)
            $table->unsignedInteger('kdf_ops');            // Argon2id opslimit
            $table->unsignedBigInteger('kdf_mem');         // Argon2id memlimit
            $table->text('wrapped_vault_key');             // VK sealed with passphrase-derived key
            $table->text('wrap_nonce');
            $table->text('wrapped_vault_key_recovery')->nullable(); // VK sealed with recovery code
            $table->text('recovery_nonce')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault');
    }
};
