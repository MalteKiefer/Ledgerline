<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The cross-user zero-knowledge sharing stack (SharedVault + SharedFolder) is
 * removed: its only consumers were the deleted password manager and the
 * already-removed files-shared-folders. Drop the vault containers, their sealed
 * manifest stores, member rows and the shared-folder blob ledger. No data bridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shared_folder_blobs');
        Schema::dropIfExists('shared_vault_stores');
        Schema::dropIfExists('shared_vault_members');
        Schema::dropIfExists('shared_vaults');
    }

    public function down(): void
    {
        // One-way teardown: the ZK shared-vault stack is not recreated on rollback.
    }
};
