<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records who uploaded each raw blob (bytes are stored at upload time, before a
// StoredFile row exists). Lets the sync reject a manifest row that references a
// blob the caller never uploaded (cross-user blob-reassignment IDOR) and lets a
// sweeper reclaim blobs that were uploaded but never synced (disk-fill DoS).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_blobs');
    }
};
