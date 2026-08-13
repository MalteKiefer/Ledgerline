<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend cross-user sharing from folders to single files. A folder_shares row
 * now targets EITHER a folder (file_folder_id → whole subtree, unchanged) OR a
 * single file (file_id → exactly that one file). file_folder_id becomes nullable
 * so a file-share leaves it empty; the two columns are mutually exclusive
 * (exactly one set), enforced by the controller (not a DB CHECK, for portability).
 *
 * Additive: existing folder shares keep file_folder_id set and file_id null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_shares', function (Blueprint $table): void {
            $table->foreignId('file_id')->nullable()->after('file_folder_id')
                ->constrained('files')->cascadeOnDelete();
            // A file-share carries no folder — allow the folder column to be null.
            $table->unsignedBigInteger('file_folder_id')->nullable()->change();
            // One share row per (owner, file), mirroring the (owner, folder) unique.
            $table->unique(['owner_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::table('folder_shares', function (Blueprint $table): void {
            $table->dropUnique(['owner_id', 'file_id']);
            $table->dropConstrainedForeignId('file_id');
            $table->unsignedBigInteger('file_folder_id')->nullable(false)->change();
        });
    }
};
