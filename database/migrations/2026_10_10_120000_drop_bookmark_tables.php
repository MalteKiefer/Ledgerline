<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Tear down the dead per-bookmark storage. Bookmarks now live entirely inside
 * the zero-knowledge store (vault_store, one sealed manifest per user), so the
 * per-row `bookmarks` and `bookmark_folders` tables have no remaining
 * server-side use. One-way cleanup: there is no rollback path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('bookmarks');
            Schema::dropIfExists('bookmark_folders');
        });
    }

    public function down(): void
    {
        // One-way cleanup; the removed tables have no rollback path.
    }
};
