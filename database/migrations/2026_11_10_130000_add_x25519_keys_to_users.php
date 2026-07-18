<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-user X25519 key pair (public key + wrapped secret key) and a
 * fingerprint to the users table. These columns are populated by the client when
 * a user first registers their key pair; until then they are NULL. They are never
 * mass-assignable — only set server-side via forceFill() in UserKeyController
 * (Task 3) after the client sends a signed key registration request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('x25519_public_key')->nullable()->after('locale');
            $table->text('wrapped_x25519_secret_key')->nullable()->after('x25519_public_key');
            $table->string('public_key_fingerprint')->nullable()->after('wrapped_x25519_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['x25519_public_key', 'wrapped_x25519_secret_key', 'public_key_fingerprint']);
        });
    }
};
