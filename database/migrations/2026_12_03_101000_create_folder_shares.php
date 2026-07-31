<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext cross-user folder sharing (pivot). Rebuilds the removed ZK
 * SharedVault/PQ-KEM "shared folder" as a plaintext, owner-scoped, role-gated
 * grant: an owner shares one of their own file_folders with another registered
 * user (see folder_share_members) who may then see and — as an editor — mutate
 * files inside that folder's subtree. No crypto: confidentiality-at-rest is
 * infra (LUKS + encrypted backups); access is enforced by FolderSharePolicy.
 *
 * One share row per (owner, folder); recipients are rows in
 * folder_share_members. Cascades on user + folder deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('file_folder_id')->constrained('file_folders')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'file_folder_id']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_shares');
    }
};
