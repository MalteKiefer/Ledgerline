<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store v3 (spec §6.3/§10/§13-A1): adds the per-user ML-KEM-768 identity material
 * alongside the existing X25519 pair, so cross-user sharing/identity wraps become
 * post-quantum hybrid (X25519 + ML-KEM-768).
 *
 *   - mlkem_public_key         plaintext encapsulation key (ek, ~1184 B → b64 ~1580 B),
 *                              non-secret, published like x25519_public_key.
 *   - wrapped_mlkem_secret_key sealed decapsulation key (dk), ciphertext under the VK.
 *
 * Both are `text` (ample headroom). Never mass-assignable — set server-side via
 * forceFill() in UserKeyController only, exactly like the X25519 columns.
 *
 * The existing `shared_vault_members.wrapped_vault_key` column is already `text`,
 * which comfortably holds the larger hybrid envelope ({suite,epk,kem_ct,c,n},
 * ~3 KB b64) — no widening needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('mlkem_public_key')->nullable()->after('public_key_fingerprint');
            $table->text('wrapped_mlkem_secret_key')->nullable()->after('mlkem_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mlkem_public_key', 'wrapped_mlkem_secret_key']);
        });
    }
};
