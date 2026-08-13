<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Public, tokenised read-only download links for a single stored file.
// Optionally time-boxed (expires_at) and password-gated (hashed).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_public_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('stored_file_id');
            $table->timestamp('expires_at')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
            $table->foreign('stored_file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->index('stored_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_public_links');
    }
};
