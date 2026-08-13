<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge contacts avatar storage: an opaque content-blob ownership
 * ledger (quota + access control + orphan reclaim). Contact records themselves
 * live in the sealed /store workspace manifest; only the (optional) avatar
 * images are stored here as opaque ciphertext blobs. The server holds no contact
 * data — no names, numbers or images in the clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_blobs');
    }
};
