<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Gallery). Album↔photo membership with an ordering
 * position. Both sides cascade on delete; a photo can appear in an album once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_album_photo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->foreignId('gallery_photo_id')->constrained('gallery_photos')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['gallery_album_id', 'gallery_photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_album_photo');
    }
};
