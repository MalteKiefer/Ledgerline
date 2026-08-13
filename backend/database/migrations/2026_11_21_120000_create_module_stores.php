<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store v3 extension (per-module split): each monolith module (notes, todos,
 * bookmarks, contacts, invoices, passwords, health, sharing) gets its OWN opaque
 * sealed store row so a mutation in one module never re-seals the others. One
 * generic keyed table (user_id + module composite key) reuses a single
 * SealedManifestStore-style protocol for all of them — ciphertext + version,
 * server stays zero-knowledge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_stores', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 32);
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->primary(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_stores');
    }
};
