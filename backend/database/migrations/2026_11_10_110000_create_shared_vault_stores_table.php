<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-shared-vault opaque store. One row per vault; the sealed_manifest is pure
 * ciphertext — the server cannot read any structure or item count. version drives
 * optimistic concurrency to prevent silent concurrent overwrites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_vault_stores', function (Blueprint $table): void {
            $table->uuid('vault_id')->primary();
            $table->foreign('vault_id')->references('id')->on('shared_vaults')->cascadeOnDelete();
            $table->text('sealed_manifest')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_vault_stores');
    }
};
