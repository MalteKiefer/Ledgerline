<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account fetch interval (minutes). Null = use the workspace default
 * config('mail_archive.sync_interval_minutes'). The scheduler runs every minute
 * and each account decides due-ness from this value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sync_interval_minutes')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('sync_interval_minutes');
        });
    }
};
