<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photo_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['gallery_photo_id', 'created_at']);
        });

        Schema::create('gallery_photo_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['gallery_photo_id', 'user_id']); // one reaction per user per photo
        });

        Schema::create('gallery_upload_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // owner
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_upload_links');
        Schema::dropIfExists('gallery_photo_reactions');
        Schema::dropIfExists('gallery_photo_comments');
    }
};
