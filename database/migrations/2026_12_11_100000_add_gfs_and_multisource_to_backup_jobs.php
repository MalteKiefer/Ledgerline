<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backup overhaul: a job may back up MULTIPLE sources at once (sources[]), choose
 * full vs. incremental (blob sources only), and rotate with grandfather-father-son
 * retention (keep_daily / keep_weekly / keep_monthly). The legacy `source` +
 * `retention` columns stay for back-compat; a job with no sources[] falls back to
 * [source], and GFS falls back to keep_daily = retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->json('sources')->nullable()->after('source');
            $table->string('mode', 16)->default('full')->after('sources'); // full|incremental
            $table->unsignedInteger('keep_daily')->nullable()->after('retention');
            $table->unsignedInteger('keep_weekly')->nullable()->after('keep_daily');
            $table->unsignedInteger('keep_monthly')->nullable()->after('keep_weekly');
        });

        // Seed sources[] + keep_daily from the existing single-source flat retention.
        foreach (DB::table('backup_jobs')->get(['id', 'source', 'retention']) as $row) {
            DB::table('backup_jobs')->where('id', $row->id)->update([
                'sources' => json_encode([$row->source]),
                'keep_daily' => $row->retention ?: 7,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->dropColumn(['sources', 'mode', 'keep_daily', 'keep_weekly', 'keep_monthly']);
        });
    }
};
