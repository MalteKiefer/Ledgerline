<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge gallery storage: an opaque content-blob ownership ledger
 * (quota + access control + orphan reclaim) and a single sealed gallery-index
 * ciphertext per user. The server holds no photo bytes, metadata or structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('gallery_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_store');
        Schema::dropIfExists('gallery_blobs');
    }
};
