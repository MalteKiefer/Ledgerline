<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable password policy + a workspace force-2FA toggle on the
 * single app_settings row. NULL pw_min_length inherits the code default (12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->integer('pw_min_length')->nullable();
            $table->boolean('pw_require_mixed_case')->default(false);
            $table->boolean('pw_require_numbers')->default(false);
            $table->boolean('pw_require_symbols')->default(false);
            $table->boolean('pw_check_breaches')->default(false);
            $table->boolean('force_2fa')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['pw_min_length', 'pw_require_mixed_case', 'pw_require_numbers', 'pw_require_symbols', 'pw_check_breaches', 'force_2fa']);
        });
    }
};
