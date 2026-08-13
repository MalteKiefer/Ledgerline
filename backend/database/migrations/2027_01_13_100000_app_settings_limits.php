<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable session/auth lifetimes, retention windows, and the Files quota
 * on the workspace singleton. NULL = inherit the env/config default; a set value
 * overrides config('...') at boot (see AppServiceProvider::SETTING_OVERRIDES).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->integer('files_quota_mb')->nullable();
            $table->integer('sanctum_expiration_minutes')->nullable();
            $table->integer('session_lifetime_minutes')->nullable();
            $table->integer('device_wipe_grace_minutes')->nullable();
            $table->integer('device_idle_days')->nullable();
            $table->integer('audit_retention_days')->nullable();
            $table->integer('access_log_retention_days')->nullable();
            $table->integer('request_log_retention_days')->nullable();
            $table->integer('backup_stale_hours')->nullable();
            $table->integer('mail_log_retention_days')->nullable();
            $table->integer('mail_blob_orphan_grace_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'files_quota_mb', 'sanctum_expiration_minutes', 'session_lifetime_minutes',
                'device_wipe_grace_minutes', 'device_idle_days', 'audit_retention_days',
                'access_log_retention_days', 'request_log_retention_days', 'backup_stale_hours',
                'mail_log_retention_days', 'mail_blob_orphan_grace_hours',
            ]);
        });
    }
};
