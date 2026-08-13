<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge Explore track storage: an opaque content-blob ownership ledger
 * (quota + access control + orphan reclaim). Explore records (tracks, couplings,
 * tolerances) themselves live sealed in the `explore` module store; only the
 * optional raw track files are stored here as opaque ciphertext blobs. The server
 * holds no Explore data in the clear — no coordinates, no track names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explore_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explore_blobs');
    }
};
