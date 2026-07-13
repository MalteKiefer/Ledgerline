<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incremental mirror state for file/gallery backup jobs. Instead of scanning
 * every blob on the disk every run, the mirror uploads only blobs created since
 * `mirror_cursor` (a high-water mark from the blob ledgers) and does the full
 * list-and-prune reconcile only once per `last_full_mirror_at` window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->timestamp('mirror_cursor')->nullable()->after('last_run_at');
            $table->timestamp('last_full_mirror_at')->nullable()->after('mirror_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->dropColumn(['mirror_cursor', 'last_full_mirror_at']);
        });
    }
};
