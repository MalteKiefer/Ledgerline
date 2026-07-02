<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-limited public share links for notes.
 *
 * A share is a frozen, client-encrypted snapshot of a single note. The server
 * stores only the ciphertext, an expiry and optional view counters — it can
 * never read the note. The decryption key lives in the link fragment (never
 * sent to the server) or, for password-protected shares, is wrapped with a key
 * the recipient derives from the password in their browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->longText('cipher');
            $table->string('nonce');

            // Password mode: the share key wrapped with a password-derived key,
            // plus the public KDF parameters needed to re-derive it.
            $table->boolean('has_password')->default(false);
            $table->longText('wrapped_key')->nullable();
            $table->string('wrap_salt')->nullable();
            $table->string('wrap_nonce')->nullable();
            $table->unsignedInteger('wrap_ops')->nullable();
            $table->unsignedBigInteger('wrap_mem')->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('max_views')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_shares');
    }
};
