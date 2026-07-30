<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Explore migrated to plaintext-relational (explore_tracks/couplings/settings +
 * plaintext raw track files on disk). Drop the zero-knowledge track-blob ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('explore_blobs');
    }

    public function down(): void
    {
        // One-way teardown.
    }
};
