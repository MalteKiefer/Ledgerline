<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First-party user management: replaces OIDC-only auth. Adds a first-party role
 * (admin|user), Fortify two-factor columns, and per-user resource overrides
 * (storage quota + device cap). The lowest-id existing user becomes the admin.
 *
 * `role` is the privilege boundary (drives the admin gate) — like `groups` it is
 * NEVER mass-assignable; set only server-side. `email` stays DB-nullable (a prior
 * migration made it so for unverified-OIDC users); first-party auth enforces
 * required+unique at the validation layer instead of a risky NOT NULL change on
 * the live table. The per-user override columns are null = fall back to the
 * workspace default (config/AppSettings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 16)->default('user')->after('email');
            // Fortify two-factor (mirrors laravel/fortify's published migration).
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Per-user resource overrides (null → workspace default).
            $table->unsignedInteger('files_quota_mb')->nullable()->after('locale');
            $table->unsignedInteger('gallery_quota_mb')->nullable()->after('files_quota_mb');
            $table->unsignedInteger('max_connected_devices')->nullable()->after('gallery_quota_mb');
        });

        // The first (lowest-id) existing user is the admin — replaces the old
        // count()<=1 / POCKETID_ADMIN_GROUP heuristic with an explicit role.
        $firstId = DB::table('users')->orderBy('id')->value('id');
        if ($firstId !== null) {
            DB::table('users')->where('id', $firstId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'role',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'files_quota_mb',
                'gallery_quota_mb',
                'max_connected_devices',
            ]);
        });
    }
};
