<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Explore). Links a gallery photo to a track. photo_id
 * is an opaque gallery photo id (the gallery is still zero-knowledge — this is only
 * a reference string, never gallery content). One coupling per photo per user.
 * The resolved lat/lng is a low-precision map coordinate (dec6) kept plaintext for
 * fast map rendering; source records how it was derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explore_couplings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('explore_track_id')->constrained('explore_tracks')->cascadeOnDelete();
            $table->string('photo_id', 64);
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lng', 10, 6)->nullable();
            $table->string('source', 24)->nullable(); // exif|interpolated|manual
            $table->timestamps();

            $table->unique(['user_id', 'photo_id']);
            $table->index('explore_track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explore_couplings');
    }
};
