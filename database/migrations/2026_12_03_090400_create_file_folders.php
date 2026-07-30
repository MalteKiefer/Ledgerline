<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Files core). Folder tree as owner-scoped rows —
 * the opaque sealed files index (files_store) is being retired for personal
 * files. Self-referential parent FK nulls children on hard-delete; soft-delete
 * hides a folder + its descendants (handled in the controller transaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('file_folders')->nullOnDelete();
            $table->string('name', 500);
            // Optimistic concurrency for the rare concurrent-tab edit (no merge engine).
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_folders');
    }
};
