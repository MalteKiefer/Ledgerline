<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // To-dos are no longer zero-knowledge: they live as plain rows in the
        // database (encryption is kept only for Mail and Files).
        Schema::create('todo_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('todos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('todo_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('priority')->default('normal'); // low | normal | high
            $table->boolean('marked')->default(false);
            $table->json('tags')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->json('reminder_channels')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
        Schema::dropIfExists('todo_lists');
    }
};
