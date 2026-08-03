<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            // The id of the currently-running ingest batch (Bus::batch), so a
            // user can cancel an in-flight sync. Nullable; cleared when the
            // account settles back to idle.
            $table->string('sync_batch_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('sync_batch_id');
        });
    }
};
