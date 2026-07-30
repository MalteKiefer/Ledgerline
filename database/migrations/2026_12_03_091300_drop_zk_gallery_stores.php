<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Gallery core teardown). Drops the zero-knowledge
 * gallery store + blob ledger now that photos/albums live as plaintext rows in
 * gallery_photos / gallery_albums (bytes at gallery/<uuid> on the file disk):
 *
 *  - gallery_store  : sealed opaque photo/album/people index (per user).
 *  - gallery_blobs  : ciphertext blob ownership ledger.
 *
 * Also drops the legacy, unused ML-shadow tables (faces / people / photos) left
 * over from an earlier iteration — they carry no Eloquent model and nothing reads
 * them after the pivot (the CLIP/face-recognition feature is deferred).
 *
 * Irreversible (down is a no-op): the sealed ciphertext is meaningless once the
 * client keys are gone and the relational gallery is authoritative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gallery_blobs');
        Schema::dropIfExists('gallery_store');
        Schema::dropIfExists('faces');
        Schema::dropIfExists('people');
        Schema::dropIfExists('photos');
    }

    public function down(): void
    {
        // No-op: the zero-knowledge gallery store is retired; its sealed ciphertext
        // cannot be reconstructed and the relational gallery has replaced it.
    }
};
