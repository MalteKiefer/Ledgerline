<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notes are no longer zero-knowledge: plain rows in the database
        // (encryption is kept only for Mail and Files).
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->default('');
            $table->longText('content')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('pinned')->default(false);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
