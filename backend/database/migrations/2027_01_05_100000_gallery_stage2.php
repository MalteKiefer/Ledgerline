<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery phase 2: EXIF capture metadata (taken_at/camera/GPS) + albums.
 * Additive — existing photos keep null EXIF and grid/timeline falls back to
 * created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->timestamp('taken_at')->nullable()->after('height');
            $table->string('camera', 191)->nullable()->after('taken_at');
            $table->decimal('lat', 10, 7)->nullable()->after('camera');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->json('exif')->nullable()->after('lng');
            $table->index(['user_id', 'taken_at']);
        });

        Schema::create('gallery_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedBigInteger('cover_photo_id')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('gallery_album_photo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_photo_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gallery_album_id', 'gallery_photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_album_photo');
        Schema::dropIfExists('gallery_albums');
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn(['taken_at', 'camera', 'lat', 'lng', 'exif']);
        });
    }
};
