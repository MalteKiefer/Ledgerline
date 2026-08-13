<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user mail reader display preferences. Both default OFF:
 *   mail_load_remote  — load remote images / content in the reader
 *                       (tracking-pixel protection when off).
 *   mail_allow_scripts — run scripts inside the sandboxed HTML body iframe
 *                       (dangerous; off by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('mail_load_remote')->default(false)->after('id');
            $table->boolean('mail_allow_scripts')->default(false)->after('mail_load_remote');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['mail_load_remote', 'mail_allow_scripts']);
        });
    }
};
