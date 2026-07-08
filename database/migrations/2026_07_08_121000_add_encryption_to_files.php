<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge encryption columns for the Files module. For an encrypted file
 * the browser wraps everything client-side: enc_metadata holds the sealed
 * name/mime/size and enc_file_key holds the per-file content key wrapped with
 * the vault key. The plaintext name/mime stay null and is_encrypted is true.
 * Folder names are stored as the sealed {c,n} JSON string in the existing name
 * column, flagged by is_encrypted. Versions carry their own sealed metadata +
 * file key so a restore can decrypt the snapshotted blob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->longText('enc_metadata')->nullable()->after('name');
            $table->text('enc_file_key')->nullable()->after('enc_metadata');
            $table->boolean('is_encrypted')->default(false)->after('enc_file_key');
            $table->string('name')->nullable()->change();
            $table->string('mime')->nullable()->change();
        });

        Schema::table('file_versions', function (Blueprint $table): void {
            $table->longText('enc_metadata')->nullable()->after('name');
            $table->text('enc_file_key')->nullable()->after('enc_metadata');
            $table->boolean('is_encrypted')->default(false)->after('enc_file_key');
        });

        Schema::table('file_folders', function (Blueprint $table): void {
            $table->boolean('is_encrypted')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['enc_metadata', 'enc_file_key', 'is_encrypted']);
        });
        Schema::table('file_versions', function (Blueprint $table): void {
            $table->dropColumn(['enc_metadata', 'enc_file_key', 'is_encrypted']);
        });
        Schema::table('file_folders', function (Blueprint $table): void {
            $table->dropColumn('is_encrypted');
        });
    }
};
