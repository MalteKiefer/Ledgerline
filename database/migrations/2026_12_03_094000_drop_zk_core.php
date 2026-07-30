<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Final pivot step: the zero-knowledge crypto core is gone. All 11 modules are
 * plaintext-relational, so the sealed encryption vault, the per-module opaque
 * stores, the public-share records and the blob/shard forensic trail have no
 * remaining producer or consumer. Drop them, plus the now-unused per-user
 * asymmetric identity-key columns (X25519 + ML-KEM-768) and the legacy OIDC sub.
 *
 * One-way teardown (no data bridge; the owner keeps external backups). down() is
 * intentionally a no-op — the ZK core is not recreated on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('blob_audit_log');
        Schema::dropIfExists('module_stores');
        Schema::dropIfExists('public_shares');
        Schema::dropIfExists('vaults');

        // oidc_sub carried a UNIQUE index; drop it first so SQLite (used in tests)
        // can drop the column (Postgres would cascade, but SQLite rebuilds the table
        // and rejects an index that references a dropped column).
        if (Schema::hasColumn('users', 'oidc_sub')) {
            Schema::table('users', function ($table): void {
                $table->dropUnique(['oidc_sub']);
            });
        }

        Schema::table('users', function ($table): void {
            foreach ([
                'x25519_public_key',
                'wrapped_x25519_secret_key',
                'mlkem_public_key',
                'wrapped_mlkem_secret_key',
                'public_key_fingerprint',
                'oidc_sub',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // One-way teardown: the ZK core (vault, module stores, public shares,
        // blob audit trail, identity keypairs) is not recreated on rollback.
    }
};
