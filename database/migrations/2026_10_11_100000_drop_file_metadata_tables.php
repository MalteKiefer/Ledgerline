<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge Files: the directory tree (folder/file names, hierarchy, tags,
 * notes, favourites, trash flags, version history) now lives inside the user's
 * sealed opaque store, not in server-side rows. Drop the metadata tables — the
 * server keeps only the opaque content blobs + their ownership ledger
 * (file_blobs, retained). Also drop the long-dead, ZK-incompatible public-link
 * and file-request features (they FK into files/file_folders and were removed
 * when Files became zero-knowledge). Forward-only; content bytes are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dead link features first — they FK into the tables dropped below.
        Schema::dropIfExists('file_public_links');
        Schema::dropIfExists('upload_links');

        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('file_folders');
    }

    public function down(): void
    {
        // One-way: the metadata now lives in the sealed store and cannot be
        // reconstructed server-side. Fresh installs never create these tables.
    }
};
