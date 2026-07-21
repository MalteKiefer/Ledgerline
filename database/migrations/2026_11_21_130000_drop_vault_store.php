<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Store v3 per-module split: the monolith workspace manifest table (vault_store)
 * is retired. Every module now has its own sealed row in module_stores and the
 * files index has its own sharded store (files_store). Clean slate — the old
 * ciphertext is not migrated (data loss of the monolith blob is accepted, the
 * client rebuilds each module store on first save).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vault_store');
    }

    public function down(): void
    {
        Schema::create('vault_store', function ($table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }
};
