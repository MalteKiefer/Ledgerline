<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keeps prior blobs of a file when its content changes on sync, so a re-upload
// no longer permanently discards the previous bytes. Versions are downloadable
// as a safety net; capped per file and pruned with the file.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('file_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->uuid('blob');
            $table->timestamp('created_at')->nullable();

            $table->index(['file_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
