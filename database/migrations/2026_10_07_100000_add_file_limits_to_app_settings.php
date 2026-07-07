<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin-tunable global file limits (null = fall back to config/env default).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('files_quota_mb')->nullable();
            $table->unsignedInteger('files_max_upload_mb')->nullable();
            $table->unsignedInteger('files_trash_retention_days')->nullable();
            $table->unsignedInteger('files_archive_max_entries')->nullable();
            $table->unsignedInteger('files_archive_max_mb')->nullable();
            $table->unsignedInteger('files_blob_orphan_grace_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'files_quota_mb', 'files_max_upload_mb', 'files_trash_retention_days',
                'files_archive_max_entries', 'files_archive_max_mb', 'files_blob_orphan_grace_hours',
            ]);
        });
    }
};
