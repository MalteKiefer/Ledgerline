<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server monitoring over plain SSH — no agent on the target host.
 *
 * `servers` holds the connection: host/port/user plus the credential, which is
 * an encrypted array (password OR private key + passphrase) and never leaves the
 * server. `host_fingerprint` pins the target's host key after the first
 * confirmed connection so a later MITM cannot capture those credentials.
 *
 * `server_facts` holds ONE row per collection run: the parsed snapshot as JSON
 * plus whether the run succeeded. The newest row is what the UI renders; older
 * rows are the trend history and are pruned on a retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username');
            // password | key — which field of `credentials` carries the secret.
            $table->string('auth_type', 16)->default('key');
            $table->text('credentials')->nullable();
            // Base64 SHA-256 of the target's host key, captured on the first
            // successful connection and compared on every later one.
            $table->string('host_fingerprint')->nullable();
            // The key on the target is restricted with a forced command, so it
            // can only emit the fact payload. Informational: it changes what the
            // UI promises the user, not how we connect.
            $table->boolean('restricted_key')->default(false);
            $table->string('group')->nullable();
            $table->text('note')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('server_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('ok')->default(false);
            $table->text('error')->nullable();
            $table->json('facts')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('collected_at');

            $table->index(['server_id', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_facts');
        Schema::dropIfExists('servers');
    }
};
