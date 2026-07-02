<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let folders carry tags, mirroring the file_tag pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_tag', function (Blueprint $table): void {
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['folder_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_tag');
    }
};
