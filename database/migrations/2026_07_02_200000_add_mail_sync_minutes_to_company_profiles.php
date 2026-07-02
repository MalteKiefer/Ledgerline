<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often the browser refreshes mail stats and headers in the background
 * while the vault is unlocked. At least 5 minutes and never longer than the
 * vault idle timeout (enforced in validation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('mail_sync_minutes')->default(5)->after('vault_idle_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn('mail_sync_minutes');
        });
    }
};
