<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's configured HKP (HTTP Keyserver Protocol) public-keyservers — e.g.
 * keys.openpgp.org, keyserver.ubuntu.com, or a self-hosted one. Used to search
 * for a recipient's public key, refresh an already-saved one, publish an own
 * key, and check whether an own key is already published. Non-secret
 * (a server URL is operational config, not credentials); owner-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_servers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 500);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_servers');
    }
};
