<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Gallery). One row per album; photos are attached
 * through the gallery_album_photo pivot. cover_photo_id points at the album's
 * cover (nulled if that photo is force-deleted). Owner-scoped per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 300);
            $table->foreignId('cover_photo_id')->nullable()->constrained('gallery_photos')->nullOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_albums');
    }
};
