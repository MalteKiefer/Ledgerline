<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-secret client-correlation fields on a device token: a device-local random
 * install id (lets the server audit trail be joined with the client's own
 * diagnostic log) plus the app/OS version reported at pairing time. All optional,
 * all non-sensitive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('install_id', 64)->nullable()->after('name');
            $table->string('app_version', 32)->nullable()->after('install_id');
            $table->string('os_version', 32)->nullable()->after('app_version');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['install_id', 'app_version', 'os_version']);
        });
    }
};
