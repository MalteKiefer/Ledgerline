<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the file-limit settings left dead by the zero-knowledge migration. Trash
 * retention (no server-side trash under ZK — trash is a manifest flag) and the
 * archive caps (server-side zip create/extract needs the plaintext the server no
 * longer has) are no longer read anywhere. Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            foreach (['files_trash_retention_days', 'files_archive_max_entries', 'files_archive_max_mb'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // One-way: the features these configured are gone under zero-knowledge.
    }
};
