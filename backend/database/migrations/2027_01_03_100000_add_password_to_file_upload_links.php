<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional password gate for public inbound upload links (Argon2id hash, never
 * serialized). Expiry is already present + is now required at creation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_upload_links', function (Blueprint $table): void {
            $table->string('password_hash')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('file_upload_links', function (Blueprint $table): void {
            $table->dropColumn('password_hash');
        });
    }
};
