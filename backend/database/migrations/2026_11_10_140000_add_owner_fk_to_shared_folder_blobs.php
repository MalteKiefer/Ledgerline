<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Give shared_folder_blobs.owner_id a real foreign key (previously only an index).
// Defence-in-depth: a user delete now reclaims that user's shared-folder blob rows
// directly via cascade, not only via the vault_id → shared_vaults cascade. The
// synchronous SharedData GDPR contributor deletes the disk bytes first; this FK
// backstops the row cleanup. Table is empty on the live server (post-clean-slate),
// so no data backfill is required.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_folder_blobs', function (Blueprint $table): void {
            // Drop the plain index first; the FK creates its own supporting index.
            $table->dropIndex(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shared_folder_blobs', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->index('owner_id');
        });
    }
};
