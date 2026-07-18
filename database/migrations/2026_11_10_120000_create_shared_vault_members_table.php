<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership ledger for shared password-Tresore. Stores the per-recipient
 * wrapped vault key (client-encrypted to the recipient's x25519 public key) and
 * their role. The server never holds the vault key in cleartext; it only routes
 * the ciphertext to the intended recipient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_vault_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('vault_id');
            $table->foreign('vault_id')->references('id')->on('shared_vaults')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('wrapped_vault_key');
            $table->string('recipient_fingerprint')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['vault_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_vault_members');
    }
};
