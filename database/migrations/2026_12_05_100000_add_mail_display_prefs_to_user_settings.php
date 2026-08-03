<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            // Per-user mail-archive rendering choices. Both default OFF (safest):
            // remote content (tracking pixels / external resources) stays blocked
            // and mail scripts do not run until the owner explicitly opts in.
            $table->boolean('mail_load_remote')->default(false);
            $table->boolean('mail_allow_scripts')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['mail_load_remote', 'mail_allow_scripts']);
        });
    }
};
