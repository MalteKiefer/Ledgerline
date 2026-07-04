<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings for the Downloads center: the maximum size of a single zip part
 * (larger exports split into several parts) for files and gallery, and which
 * channels announce a finished export.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            // 0 = unlimited (one zip). Otherwise a part is closed before it would
            // exceed this many megabytes.
            $table->unsignedInteger('export_files_max_zip_mb')->default(2048);
            $table->unsignedInteger('export_gallery_max_zip_mb')->default(2048);
            $table->boolean('export_notify_desktop')->default(true);
            $table->boolean('export_notify_ntfy')->default(false);
            $table->boolean('export_notify_mail')->default(false);
            $table->boolean('export_notify_webhook')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'export_files_max_zip_mb',
                'export_gallery_max_zip_mb',
                'export_notify_desktop',
                'export_notify_ntfy',
                'export_notify_mail',
                'export_notify_webhook',
            ]);
        });
    }
};
