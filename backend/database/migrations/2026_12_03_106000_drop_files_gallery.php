<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ersatzlos removal of the Files + Gallery modules (finance-only pivot).
 *
 * Drops every Files/Gallery table. Children (referencing tables) are dropped
 * before their parents to respect foreign keys; dropIfExists is idempotent, so
 * re-running is a no-op. On pgsql the GIN/hnsw indexes drop with their tables.
 *
 * Irreversible — the module code + data are gone, there is nothing to recreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Files: version rows + shares reference files/file_folders; drop them,
        // then files, then the folder tree.
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('file_shares');
        Schema::dropIfExists('folder_share_members');
        Schema::dropIfExists('folder_shares');
        Schema::dropIfExists('files');
        Schema::dropIfExists('file_folders');

        // Gallery: pivot + shares + faces reference photos/albums/people; drop
        // those first, then albums (cover_photo_id → photos) and people, then
        // photos last.
        Schema::dropIfExists('gallery_album_photo');
        Schema::dropIfExists('gallery_shares');
        Schema::dropIfExists('gallery_faces');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('gallery_people');
        Schema::dropIfExists('gallery_photos');
    }

    public function down(): void
    {
        // Irreversible: Files + Gallery were removed wholesale in the finance-only
        // pivot. No-op.
    }
};
