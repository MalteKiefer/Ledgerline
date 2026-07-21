<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store v3 (spec §4.2/§13-A10b): Files graduates to its own sharded sealed store,
 * out of the monolith workspace `/store`. Same shape as gallery_store / vault_store
 * — one opaque ciphertext root + optimistic-concurrency version per user. The heavy
 * file records live in content-addressed shard blobs (files disk ledger), so files
 * churn no longer re-seals notes/todos/passwords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files_store');
    }
};
