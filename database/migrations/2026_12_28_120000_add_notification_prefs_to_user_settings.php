<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-category push preferences: { "<category>": { "push": bool } }.
 * SendPushJob honours these to suppress unwanted pushes server-side (the
 * notification-centre row is still created; only the push fan-out is skipped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->json('notification_prefs')->nullable()->after('mail_signature');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('notification_prefs');
        });
    }
};
