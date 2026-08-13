<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Gallery). Public /gallery-share/{token} links for
 * an album or a single photo. Bytes are served plaintext (no fragment key like
 * the ZK PublicShare); the optional password is a rate-limited access gate, not
 * an encryption root. Owner-scoped explicitly in the controllers so the public
 * (unauthenticated) routes can resolve a link by token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('kind', 16); // album|photo
            $table->foreignId('gallery_album_id')->nullable()->constrained('gallery_albums')->nullOnDelete();
            $table->foreignId('gallery_photo_id')->nullable()->constrained('gallery_photos')->nullOnDelete();
            $table->string('password_hash')->nullable();
            $table->boolean('allow_download')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_shares');
    }
};
