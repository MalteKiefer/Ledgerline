<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot, Phase 1 (Todos). Lists + tasks as rows; a task's
 * list FK nulls on list delete. Per-row writes + FK + soft-delete replace the
 * opaque sealed store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('todos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('todo_list_id')->nullable()->constrained('todo_lists')->nullOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('priority', 10)->default('normal'); // high | normal | low
            $table->boolean('marked')->default(false);
            $table->boolean('done')->default(false);
            $table->json('tags')->nullable();
            $table->timestamptz('due')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'done']);
            $table->index(['user_id', 'todo_list_id']);
            $table->index(['user_id', 'marked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
        Schema::dropIfExists('todo_lists');
    }
};
