<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ownership ledger for a shared folder's content blobs (shared-folders/{blob}).
// vault_id ties the blob to its shared folder (membership drives access);
// owner_id is the FOLDER OWNER (quota attribution), stamped server-side from the
// vault, never the uploader. Mirrors file_blobs but scoped to a vault.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_folder_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->uuid('vault_id');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->foreign('vault_id')->references('id')->on('shared_vaults')->cascadeOnDelete();
            $table->index('vault_id');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_folder_blobs');
    }
};
