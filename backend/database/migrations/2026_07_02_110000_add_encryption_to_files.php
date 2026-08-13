<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add zero-knowledge encryption columns to files.
 *
 * For encrypted uploads the browser wraps everything client-side: enc_metadata
 * holds the ciphertext of the real name/mime/size (the server never sees them),
 * and enc_file_key holds the per-file key wrapped with the vault key. The plain
 * name/mime_type/extracted_text stay empty and is_encrypted is true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->longText('enc_metadata')->nullable()->after('extracted_text');
            $table->text('enc_file_key')->nullable()->after('enc_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['enc_metadata', 'enc_file_key']);
        });
    }
};
