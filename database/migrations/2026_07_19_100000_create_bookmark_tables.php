<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bookmarks are no longer zero-knowledge: plain database rows
        // (encryption is kept only for Mail and Files).
        Schema::create('bookmark_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bookmark_folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('url', 2048);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('favorite')->default(false);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('bookmark_folders');
    }
};
