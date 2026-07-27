<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes graduate to a sharded sealed store (store merge-safety spec §3b), mirroring
 * files/gallery: a per-user sealed ROOT pointer table (`notes_store`) + a blob ledger
 * (`notes_blobs`) for the content-addressed id-bucket record shards. Edits to records
 * in different shards never conflict; only same-shard concurrency 409s (and that still
 * delta-merges). Metadata leak is bounded to shard COUNT, not item count. Additive:
 * the old single-blob `module_stores` notes row stays readable until the client
 * re-shards on first save (dual-read migration), so no data move server-side (ZK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('notes_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_blobs');
        Schema::dropIfExists('notes_store');
    }
};
