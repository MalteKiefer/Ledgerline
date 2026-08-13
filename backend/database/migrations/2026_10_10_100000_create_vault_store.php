<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The opaque zero-knowledge store: one sealed manifest per user holding the whole
 * workspace (notes/bookmarks/todos and their structure/flags — files follow in a
 * later phase). The server sees only ciphertext + a version counter (optimistic
 * concurrency) + the update time. No structure, counts, types or flags are
 * server-visible. File content bytes stay as separate opaque blobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_store');
    }
};
