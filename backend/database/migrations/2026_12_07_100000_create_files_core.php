<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files module core (plaintext-relational): nested folders, files, and per-file
 * version history. Bytes live plaintext on the files disk under files/{uuid};
 * these rows hold the plaintext metadata. Owner-scoped, soft-deletes (trash),
 * optimistic version. search_text/indexed_at are populated by the Phase-2
 * full-text indexer (nullable now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 500);
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('file_folders')->nullOnDelete();
            $table->index(['user_id', 'parent_id']);
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_folder_id')->nullable()->constrained('file_folders')->nullOnDelete();
            $table->string('name', 500);
            $table->string('mime', 255)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('storage_path', 255);
            $table->string('sha256', 64)->nullable();
            $table->json('tags')->nullable();
            $table->text('note')->nullable();
            $table->boolean('favorite')->default(false);
            $table->longText('search_text')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'file_folder_id']);
            $table->index(['user_id', 'deleted_at']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('file_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 255)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['file_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('file_folders');
    }
};
