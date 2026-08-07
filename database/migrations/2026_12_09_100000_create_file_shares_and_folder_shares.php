<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files stage 4: sharing.
 *   file_shares            — public, tokenised links to a file or folder subtree
 *                            (optional password gate + expiry + download flag).
 *   folder_shares + members — cross-user folder sharing (owner → registered users,
 *                             role viewer|editor). Removal = row delete (no crypto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('kind', 16); // file|folder
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('file_folder_id')->nullable()->constrained('file_folders')->nullOnDelete();
            $table->string('password_hash')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('folder_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('file_folder_id')->constrained('file_folders')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'file_folder_id']);
            $table->index('owner_id');
        });

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
        Schema::dropIfExists('folder_shares');
        Schema::dropIfExists('file_shares');
    }
};
