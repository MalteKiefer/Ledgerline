<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive PGP/S-MIME keys — richer, non-secret metadata surfaced in the
 * key detail view (identities were already stored via `identities_json`, but
 * only ever populated for server-GENERATED keys — imported keys showed none,
 * and neither path stored algorithm/length/curve or the certificate's
 * issuer/serial/validity-start). Computed once at import/generate time from
 * the same `gpg`/`openssl` calls already made there — no new live subprocess
 * on every list/detail view. All additive + nullable, no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_pgp_keys', function (Blueprint $table): void {
            $table->string('algorithm', 32)->nullable()->after('identities_json');   // RSA / ECDSA / EdDSA / ECDH / ...
            $table->unsignedInteger('key_length')->nullable()->after('algorithm');   // bits
            $table->string('curve', 64)->nullable()->after('key_length');            // ECC curve name (pgp + smime EC certs)
            $table->string('issuer', 500)->nullable()->after('curve');               // S/MIME: cert issuer DN
            $table->string('serial', 128)->nullable()->after('issuer');              // S/MIME: cert serial (hex)
            $table->timestamp('valid_from')->nullable()->after('serial');            // pgp key creation date / smime notBefore
        });
    }

    public function down(): void
    {
        Schema::table('mail_pgp_keys', function (Blueprint $table): void {
            $table->dropColumn(['algorithm', 'key_length', 'curve', 'issuer', 'serial', 'valid_from']);
        });
    }
};
