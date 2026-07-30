<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Files core). Prior revisions of a file's bytes.
 * Each row points at its own blob on the file disk (storage_path). Rows cascade
 * on hard-delete of the parent file; count is capped per user setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('size');
            $table->string('mime', 255)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
