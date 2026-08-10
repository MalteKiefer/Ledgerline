<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: Live Photo motion + duplicate prevention.
 *
 * A Live Photo is a still (HEIC/JPEG) plus a paired .MOV. motion_path holds that
 * clip on the SAME photo row (one entry, not two). content_id is Apple's shared
 * Live Photo identifier (from the MOV's QuickTime metadata) — a robust pair key.
 * The (user_id, sha256) index backs upload de-duplication (same bytes → skip).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->string('motion_path')->nullable()->after('storage_path');
            $table->string('content_id', 191)->nullable()->after('sha256');
            $table->index(['user_id', 'sha256']);
            $table->index(['user_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'sha256']);
            $table->dropIndex(['user_id', 'content_id']);
            $table->dropColumn(['motion_path', 'content_id']);
        });
    }
};
