<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: the zero-knowledge Files store is torn down. The sealed sharded files
 * index (files_store) and the opaque content-blob ledger (file_blobs) are gone;
 * Files is now plaintext-relational (files / file_folders / file_versions).
 *
 * The on-disk blob bytes are deliberately NOT dropped: the new files share the
 * `files/` disk prefix, so the old ciphertext objects cannot be distinguished
 * from live plaintext files here. Any leftover bytes are left orphaned on disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('file_blobs');
        Schema::dropIfExists('files_store');
    }

    public function down(): void
    {
        // No-op: these zero-knowledge tables are retired by the pivot.
    }
};
