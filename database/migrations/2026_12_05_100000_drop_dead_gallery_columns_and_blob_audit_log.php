<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop dead columns + table left over from the finance-only pivot.
 *
 *  - app_settings.gallery_geocode_grid_km / .gallery_geocode_interval_ms: the
 *    gallery module (and its geocoding) was removed. NOT referenced by
 *    AppServiceProvider::SETTING_OVERRIDES (only files_max_upload_mb /
 *    files_blob_orphan_grace_hours remain there); no reader anywhere.
 *  - app_settings.export_gallery_max_zip_mb: gallery bulk export is gone; the
 *    files export cap (export_files_max_zip_mb) stays.
 *  - app_settings.files_quota_mb: the aggregate files storage quota is no longer
 *    enforced (finance-only); no reader.
 *  - user_settings.gallery_columns: the gallery grid preference is dead.
 *  - blob_audit_log: the ZK blob/shard forensic trail table has zero app
 *    readers/writers (only its create migration referenced it).
 *
 * All guarded with hasColumn/hasTable so re-runs and fresh installs are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            foreach ([
                'gallery_geocode_grid_km',
                'gallery_geocode_interval_ms',
                'export_gallery_max_zip_mb',
                'files_quota_mb',
            ] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('user_settings', 'gallery_columns')) {
                $table->dropColumn('gallery_columns');
            }
        });

        Schema::dropIfExists('blob_audit_log');
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('app_settings', 'gallery_geocode_grid_km')) {
                $table->decimal('gallery_geocode_grid_km', 6, 3)->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'gallery_geocode_interval_ms')) {
                $table->unsignedInteger('gallery_geocode_interval_ms')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'export_gallery_max_zip_mb')) {
                $table->unsignedInteger('export_gallery_max_zip_mb')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'files_quota_mb')) {
                $table->unsignedInteger('files_quota_mb')->nullable();
            }
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_settings', 'gallery_columns')) {
                $table->unsignedTinyInteger('gallery_columns')->default(6);
            }
        });

        if (! Schema::hasTable('blob_audit_log')) {
            Schema::create('blob_audit_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('module', 32);
                $table->string('action', 32);
                $table->uuid('blob')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->string('source', 16)->nullable();
                $table->string('reason', 48)->nullable();
                $table->string('result', 16)->default('ok');
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('user_id');
                $table->index('blob');
                $table->index(['module', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }
};
