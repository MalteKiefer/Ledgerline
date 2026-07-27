<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace toggle for self-service registration. Default OFF: an admin creates
 * users; when on, /register is open and requires email verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('allow_registration')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn('allow_registration');
        });
    }
};
