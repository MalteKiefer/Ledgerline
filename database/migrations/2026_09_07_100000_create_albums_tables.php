<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo albums: user-owned collections of photos, shareable with other users
 * (ResourceShare) and via a public link (PublicShare). album_photo is the
 * ordered membership pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('cover_photo_id')->nullable();
            $table->timestamps();
        });

        Schema::create('album_photo', function (Blueprint $table): void {
            $table->uuid('album_id');
            $table->unsignedBigInteger('photo_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->primary(['album_id', 'photo_id']);
            $table->foreign('album_id')->references('id')->on('albums')->cascadeOnDelete();
            $table->foreign('photo_id')->references('id')->on('photos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_photo');
        Schema::dropIfExists('albums');
    }
};
