<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notes module (plaintext-relational). A nested folder tree + per-note rows with
 * plaintext Markdown body, tags, pin/favorite, optimistic version + soft-delete.
 * Non-secret content (title/body/tags) is stored plaintext + indexed so the
 * server can search/sort. A GIN full-text index is added on Postgres only (the
 * sqlite test DB skips it). Wikilinks + attachments arrive in later stages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('note_folders')->nullOnDelete();
            $table->string('name', 500);
            $table->string('color', 32)->nullable();
            $table->integer('position')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_folder_id')->nullable()->constrained('note_folders')->nullOnDelete();
            $table->string('title', 500)->nullable();
            $table->text('body')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('pinned')->default(false);
            $table->boolean('favorite')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'pinned']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'note_folder_id']);
        });

        // Postgres full-text search over title + body (skipped on the sqlite test DB).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX notes_fts_idx ON notes USING GIN (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(body,'')))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_folders');
    }
};
