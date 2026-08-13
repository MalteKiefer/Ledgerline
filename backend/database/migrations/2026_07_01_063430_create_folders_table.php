<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the folders table: virtual, nestable folders for organising files.
 *
 * A folder may have a parent folder (self-referential); root folders have none.
 * Folders are global (shared workspace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
