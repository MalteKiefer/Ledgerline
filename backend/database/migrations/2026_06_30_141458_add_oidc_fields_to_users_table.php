<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapt the users table to OIDC-only authentication.
 *
 * Identity is owned by the Pocket-ID provider, so users are matched on their
 * stable OIDC subject identifier ("sub") rather than on a local password. The
 * password column is therefore made nullable and left unused for sign-in.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Stable, unique subject identifier issued by Pocket-ID.
            $table->string('oidc_sub')->nullable()->unique()->after('id');

            // Optional avatar URL supplied by the provider's userinfo endpoint.
            $table->string('avatar')->nullable()->after('email');

            // No local password is used; allow it to be null.
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['oidc_sub']);
            $table->dropColumn(['oidc_sub', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
