<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The in-app mail module was removed entirely. Drop its tables and the
// per-user sync-interval setting. (Migration history is kept; this is the
// teardown counterpart.)
return new class extends Migration
{
    public function up(): void
    {
        // FK-safe order: dependents first.
        Schema::dropIfExists('mail_identities');
        Schema::dropIfExists('mail_signatures');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_accounts');

        if (Schema::hasColumn('user_settings', 'mail_sync_minutes')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->dropColumn('mail_sync_minutes');
            });
        }
    }

    public function down(): void
    {
        // Irreversible teardown; the module is gone. No-op.
    }
};
