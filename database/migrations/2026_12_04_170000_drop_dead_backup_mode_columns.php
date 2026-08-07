<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead backup mirror/mode columns. The incremental-mirror path was removed
 * (no MirrorableSource implementer remains in the finance-only app — every source is
 * a full archive), so `mode`, `mirror_cursor` and `last_full_mirror_at` are vestigial:
 * the manager ignores them and the job form no longer offers a mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_jobs')) {
            return;
        }
        Schema::table('backup_jobs', function (Blueprint $table): void {
            foreach (['mode', 'mirror_cursor', 'last_full_mirror_at'] as $col) {
                if (Schema::hasColumn('backup_jobs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('backup_jobs')) {
            return;
        }
        Schema::table('backup_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('backup_jobs', 'mode')) {
                $table->string('mode')->default('archive');
            }
            if (! Schema::hasColumn('backup_jobs', 'mirror_cursor')) {
                $table->timestamp('mirror_cursor')->nullable();
            }
            if (! Schema::hasColumn('backup_jobs', 'last_full_mirror_at')) {
                $table->timestamp('last_full_mirror_at')->nullable();
            }
        });
    }
};
