<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot, Phase 1 (Bookmarks). Nested folders + bookmarks as
 * rows. A folder delete nulls its children's parent (→ root) and its bookmarks'
 * folder via FK, replacing the client-side manifest reparenting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmark_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('bookmark_folders')->nullOnDelete();
            $table->string('name', 200);
            $table->string('color', 32)->nullable();
            $table->string('icon', 32)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'parent_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bookmark_folder_id')->nullable()->constrained('bookmark_folders')->nullOnDelete();
            $table->string('title', 500)->nullable();
            $table->string('url', 2000);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('favorite')->default(false);
            $table->boolean('read_later')->default(false);
            $table->boolean('read')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'bookmark_folder_id']);
            $table->index(['user_id', 'favorite']);
            $table->index(['user_id', 'read_later']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('bookmark_folders');
    }
};
