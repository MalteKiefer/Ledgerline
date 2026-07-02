<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the workspace settings table from the legacy "company_profiles" (an
 * ERP-era name) to "app_settings". There is no company concept anymore — it is
 * simply the single global settings row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_profiles') && ! Schema::hasTable('app_settings')) {
            Schema::rename('company_profiles', 'app_settings');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_settings') && ! Schema::hasTable('company_profiles')) {
            Schema::rename('app_settings', 'company_profiles');
        }
    }
};
