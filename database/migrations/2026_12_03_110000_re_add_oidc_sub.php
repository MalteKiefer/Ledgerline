<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-add the `oidc_sub` column dropped by the ZK-core teardown, so the optional
 * Pocket-ID (OIDC) sign-in can bind an account to its stable subject identifier.
 *
 * Additive and non-destructive: it only adds a nullable, unique column to the
 * users table and touches no other table (finance data is untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'oidc_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Stable, unique subject identifier issued by Pocket-ID (nullable:
            // first-party email/password users never have one).
            $table->string('oidc_sub')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'oidc_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Drop the UNIQUE index first so SQLite (used in tests) can drop the
            // column — it rebuilds the table and rejects an index that references
            // a dropped column.
            $table->dropUnique(['oidc_sub']);
            $table->dropColumn('oidc_sub');
        });
    }
};
