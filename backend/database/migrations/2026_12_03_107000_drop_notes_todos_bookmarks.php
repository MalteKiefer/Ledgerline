<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ersatzlos-remove the Notes, Todos and Bookmarks modules as the app becomes
 * finance-only. Mirrors the earlier Contacts/Passwords removals: the relational
 * tables have no remaining server-side use once the models/controllers/routes
 * are gone. Children are dropped before their parents (todos before todo_lists,
 * bookmarks before bookmark_folders). PostgreSQL full-text indexes are dropped
 * together with their tables. One-way cleanup: there is no rollback path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('notes');
            Schema::dropIfExists('todos');
            Schema::dropIfExists('todo_lists');
            Schema::dropIfExists('bookmarks');
            Schema::dropIfExists('bookmark_folders');
        });
    }

    public function down(): void
    {
        // One-way cleanup; the removed tables have no rollback path.
    }
};
