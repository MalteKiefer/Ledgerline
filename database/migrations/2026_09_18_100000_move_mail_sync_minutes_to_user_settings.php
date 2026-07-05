<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The background mail-sync interval is a personal preference, not a workspace
 * setting: move it from the global app_settings row onto each user's
 * user_settings row. The existing global value seeds every current user so the
 * cadence they picked is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->unsignedInteger('mail_sync_minutes')->default(5);
        });

        $global = (int) (DB::table('app_settings')->value('mail_sync_minutes') ?? 5);
        if ($global >= 5) {
            DB::table('user_settings')->update(['mail_sync_minutes' => $global]);
        }

        if (Schema::hasColumn('app_settings', 'mail_sync_minutes')) {
            Schema::table('app_settings', function (Blueprint $table): void {
                $table->dropColumn('mail_sync_minutes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->unsignedInteger('mail_sync_minutes')->default(5);
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('mail_sync_minutes');
        });
    }
};
