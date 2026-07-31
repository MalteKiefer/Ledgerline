<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational Files public share links (pivot). Rebuilds the removed ZK
 * /s/{token} file/folder share as a plaintext mirror of the Gallery share:
 * public /file-share/{token} links for a single file or a folder subtree. Bytes
 * are served plaintext (no fragment key); the optional password is a
 * rate-limited access gate, not an encryption root. Owner-scoped in the
 * controllers so the public (unauthenticated) routes can resolve a link by token.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};
