<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Files core). One row per file; the bytes live
 * plaintext on the file disk at storage_path (e.g. files/{uuid}). Metadata
 * (name/mime/size/tags/note/favorite) is plaintext + indexed so the server can
 * list/search/sort. Prior revisions of the bytes go to file_versions.
 */
return new class extends Migration
{
    public function up(): void
    {
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
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'file_folder_id']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
