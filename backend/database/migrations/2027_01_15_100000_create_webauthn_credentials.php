<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WebAuthn / passkey credentials (public-key crypto — nothing secret at rest).
 * `source` is the serialized PublicKeyCredentialSource (public key + counter +
 * transports); `credential_id` (base64url) is the unique lookup handle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();
            $table->string('name')->nullable();
            $table->text('source');
            $table->string('aaguid', 64)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
