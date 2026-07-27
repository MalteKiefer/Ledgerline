<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passwords graduate to a sharded sealed store (store merge-safety spec §3b), like
 * files/gallery/notes: a per-user sealed ROOT (`passwords_store`) + a shard-blob ledger
 * (`passwords_blobs`) for the content-addressed id-bucket record shards (secrets +
 * secretFolders). Additive: the old single-blob `module_stores` passwords row stays
 * readable until web AND the extension re-shard on first save (dual-read migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passwords_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('passwords_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passwords_blobs');
        Schema::dropIfExists('passwords_store');
    }
};
