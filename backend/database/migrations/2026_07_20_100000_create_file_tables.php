<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files are no longer zero-knowledge: plain metadata rows in the
        // database, with the bytes stored (unencrypted) on the files disk.
        // Only Mail keeps encryption now. Ids are client-generated UUIDs so the
        // rich browser can create/move items optimistically before syncing.
        Schema::create('file_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->index('parent_id');
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('file_folder_id')->nullable();
            $table->string('name');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->uuid('blob'); // stored object id on the files disk (files/{blob})
            $table->json('tags')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index('file_folder_id');
            $table->index('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
        Schema::dropIfExists('file_folders');
    }
};
