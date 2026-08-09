<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive — Phase 5 (PGP / S-MIME reading). The user's own decryption keys,
 * used ONLY server-side to decrypt encrypted archived mail (at ingest, or lazily
 * at read when a key is added later). Non-ZK: the private key + its passphrase
 * are operative secrets — Laravel `encrypted` cast (APP_KEY, not in a DB dump) +
 * `$hidden` (never serialised, never returned by the API). The public material
 * (public_key / cert_pem / fingerprint / identities) is non-secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_pgp_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8);                        // pgp|smime
            $table->string('label', 200);
            $table->string('key_fingerprint')->nullable();    // non-secret
            $table->string('key_id')->nullable();             // non-secret
            $table->longText('public_key')->nullable();       // armored / non-secret
            $table->longText('private_key');                  // encrypted cast (APP_KEY)
            $table->text('passphrase')->nullable();           // encrypted cast (APP_KEY)
            $table->longText('cert_pem')->nullable();         // S/MIME recipient cert (non-secret)
            $table->json('identities_json')->nullable();      // [{name?,email}] (non-secret)
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_pgp_keys');
    }
};
