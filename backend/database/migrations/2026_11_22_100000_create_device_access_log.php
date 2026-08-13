<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight, throttled per-device access trail: when + from where each device
 * token last reached the API, coarsened to a route GROUP (never the full path
 * with ids) so it stays privacy-preserving. Written at most once per token per
 * minute by UpdateTokenIp; short retention (device-access-log:prune). Metadata
 * only — no secrets, no ciphertext, no token value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_access_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('token_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('route_group', 32)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_access_log');
    }
};
