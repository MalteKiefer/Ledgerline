<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recipient of a plaintext folder share (pivot). One row per user granted
 * access to a folder_shares row, at a role: viewer (read + download) or editor
 * (upload / rename / delete within the shared subtree). Unique per
 * (share, user); cascades when the share or the user is deleted. Access is
 * always reached through the parent share and gated by FolderSharePolicy — the
 * join row carries no owner scope of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_share_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_share_id')->constrained('folder_shares')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16); // viewer|editor
            $table->timestamps();
            $table->unique(['folder_share_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_share_members');
    }
};
