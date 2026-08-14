<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Other people's public keys / certificates you can encrypt a file to (in addition
 * to your own key). Public material only — never a private key — so nothing here is
 * a secret; it complements mail_pgp_keys (which holds YOUR keys, private encrypted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8);                     // pgp | smime
            $table->string('label', 200);
            $table->string('fingerprint')->nullable();     // pgp fingerprint (non-secret)
            $table->longText('public_key')->nullable();    // armored PGP public key
            $table->longText('cert_pem')->nullable();      // S/MIME recipient certificate (PEM)
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_recipients');
    }
};
