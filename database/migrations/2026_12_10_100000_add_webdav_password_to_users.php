<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files stage 6: WebDAV. An app-specific, revocable WebDAV password (hashed) so a
 * user can mount their files as a network drive without typing their login
 * password into Finder/Explorer. Null = WebDAV access disabled for that user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('webdav_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('webdav_password');
        });
    }
};
