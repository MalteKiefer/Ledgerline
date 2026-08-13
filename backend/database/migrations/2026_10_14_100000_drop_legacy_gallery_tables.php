<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy plaintext-gallery tables. The gallery is now zero-knowledge:
 * the browser holds all keys and the server stores only opaque, per-user sealed
 * blobs (gallery_blobs) plus one sealed manifest (gallery_store). The old
 * server-visible photo/album/people/face model is gone, so its tables are
 * removed. Forward-only: there is no meaningful down() as the models and their
 * plaintext columns no longer exist in the application.
 *
 * Dropped in foreign-key-safe order: the album_photo pivot and the faces table
 * reference photos/albums/people, so they are dropped first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('album_photo');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('faces');
        Schema::dropIfExists('people');
        Schema::dropIfExists('photos');
    }

    public function down(): void
    {
        // Forward-only: the legacy plaintext gallery has been removed entirely.
    }
};
