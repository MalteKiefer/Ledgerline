<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public, unauthenticated share links for a gallery album.
 *
 * Zero-knowledge: the server stores only the SEALED share manifest (the album's
 * photo list + per-blob keys, encrypted client-side under a share key that lives
 * in the link fragment and never reaches the server), an allow-list of the blob
 * ids the link may stream, and coarse access controls (optional password gate,
 * optional expiry). The server never sees the album name, photo content, or the
 * share key — it cannot decrypt anything it stores here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_shares', function (Blueprint $table) {
            $table->id();
            // URL-safe opaque token; the fragment (#…) carries the decryption key.
            $table->string('token', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32)->default('gallery_album');
            // Sealed (client-encrypted) manifest JSON: {c, n} secretbox ciphertext.
            $table->text('sealed_manifest');
            // Allow-list of blob UUIDs this link may stream (thumb/medium/…). The
            // public blob route refuses anything not in here.
            $table->json('blob_refs');
            // Optional password gate (bcrypt/argon hash), enforced server-side and
            // rate-limited — it is NOT the encryption root (the fragment key is).
            $table->string('password_hash')->nullable();
            $table->boolean('allow_download')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_shares');
    }
};
