<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail-independent invite / password-reset links. An admin generates a link with a
 * chosen validity; the plaintext token lives only in the URL, the server stores only
 * its SHA-256 hash. Links are single-use (used_at) and expire (expires_at). Consuming
 * a link lets the user set a password — the same power as a password reset — so the
 * consumption route is public but tightly guarded (hash_equals, expiry, single-use,
 * throttled). This removes the dependency on SMTP for onboarding and resets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // sha256 hex of the URL token
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_links');
    }
};
