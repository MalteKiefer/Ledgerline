<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived QR device-pairing sessions. The already-authenticated web session
 * generates a one-time code (shown as a QR); the native app claims it and, after
 * the owner approves the named device in the web UI, exchanges it exactly once
 * for a first-party Sanctum token. Only the SHA-256 of the code is stored; the
 * raw code lives only in the QR. Rows are single-use and pruned after expiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_pairings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash')->unique();
            $table->string('device_name')->nullable();
            // pending_scan → pending_approval → approved → consumed; rejected.
            $table->string('status')->default('pending_scan');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
    }
};
