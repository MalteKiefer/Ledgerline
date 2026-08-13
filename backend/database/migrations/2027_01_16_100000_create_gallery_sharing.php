<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery sharing: public album links (token, optional password/expiry,
 * allow_download) + internal cross-user shares (an album, or the whole gallery
 * when gallery_album_id is null) granted to another registered user (viewer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_public_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->string('token_hash', 64)->unique();
            $table->string('password_hash')->nullable();
            $table->boolean('allow_download')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'gallery_album_id']);
        });

        Schema::create('gallery_internal_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            // null = the whole gallery; otherwise a single album subtree.
            $table->foreignId('gallery_album_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index(['recipient_id']);
            $table->index(['owner_id', 'gallery_album_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_internal_shares');
        Schema::dropIfExists('gallery_public_shares');
    }
};
