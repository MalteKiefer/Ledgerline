<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Gallery core). One row per photo/video; the
 * original bytes plus the server-generated renditions (thumb/medium webp,
 * motion clip) live plaintext on the file disk at the *_path columns. Metadata
 * (kind/mime/size/dims/taken_at/lat/lng/camera/phash/favorite/description) is
 * plaintext + indexed so the server can list/group/sort. Fresh table name
 * (gallery_photos) so nothing touches the legacy `photos` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('image'); // image|video
            $table->string('mime', 120);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('taken_at')->nullable();   // plaintext — timeline grouping
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lng', 10, 6)->nullable();
            $table->string('camera', 200)->nullable();
            $table->bigInteger('phash')->nullable()->index();
            $table->boolean('favorite')->default(false);
            $table->text('description')->nullable();
            $table->string('storage_path', 255);          // original bytes on disk
            $table->string('thumb_path', 255)->nullable();
            $table->string('medium_path', 255)->nullable();
            $table->string('motion_path', 255)->nullable();
            $table->json('exif')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'taken_at']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
