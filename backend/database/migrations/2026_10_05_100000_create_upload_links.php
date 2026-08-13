<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Public, tokenised "file request" links: a visitor can only upload files into
// the owner's chosen folder — they can never see or list anything.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('file_folder_id')->nullable();
            $table->string('label')->nullable();
            $table->string('allowed_extensions')->nullable(); // csv, lowercase; null = any
            $table->timestamp('expires_at')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('max_file_mb')->nullable();
            $table->unsignedInteger('uploads')->default(0);
            $table->timestamps();
            $table->foreign('file_folder_id')->references('id')->on('file_folders')->nullOnDelete();
            $table->index('file_folder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_links');
    }
};
