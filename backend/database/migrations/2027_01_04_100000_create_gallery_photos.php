<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery module (plaintext-relational), phase 1. One row per photo; bytes live
 * plaintext on the files disk under gallery/{uuid}. EXIF (taken_at/camera/GPS)
 * + albums arrive in later phases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('name', 500);
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('favorite')->default(false);
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'favorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
