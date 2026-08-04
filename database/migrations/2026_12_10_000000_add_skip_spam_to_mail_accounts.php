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
            // When true (default), messages the origin server has flagged as
            // spam (X-Spam-Flag / rspamd / etc.) are NOT archived — they never
            // enter the immutable archive. The origin copy is untouched.
            $table->boolean('skip_spam')->default(true)->after('delete_after_import');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('skip_spam');
        });
    }
};
