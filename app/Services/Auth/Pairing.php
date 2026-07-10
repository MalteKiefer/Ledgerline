<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * QR device-pairing state machine. The web session creates a one-time code
 * (shown as a QR); the app claims it; the owner approves the named device in the
 * web UI; the app then collects a first-party Sanctum token exactly once. Only
 * the SHA-256 of the code is ever stored, and the raw token is minted at collect
 * time (never persisted). Invalid transitions abort with a 4xx.
 */
class Pairing
{
    /** Codes are valid for two minutes — long enough to scan + approve, short enough to blunt replay. */
    public const TTL_SECONDS = 120;

    /** Create a fresh pairing for the (web-authenticated) user; returns the row + the raw code. */
    public function create(User $user): array
    {
        // Discard the user's superseded, still-pending pairings so the table stays tidy.
        DevicePairing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [DevicePairing::PENDING_SCAN, DevicePairing::PENDING_APPROVAL])
            ->delete();

        $code = Str::random(43); // ~256 bits of entropy

        $pairing = DevicePairing::create([
            'user_id' => $user->id,
            'code_hash' => $this->hash($code),
            'status' => DevicePairing::PENDING_SCAN,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return ['pairing' => $pairing, 'code' => $code];
    }

    /** App claims a scanned code, attaching a device name and moving to pending-approval. */
    public function claim(string $code, string $deviceName): DevicePairing
    {
        $pairing = $this->require($code, DevicePairing::PENDING_SCAN);
        $pairing->update([
            'device_name' => Str::limit(trim($deviceName) !== '' ? trim($deviceName) : 'Unknown device', 60, ''),
            'status' => DevicePairing::PENDING_APPROVAL,
        ]);

        return $pairing;
    }

    /** Owner approves a claimed device (web). No token is minted yet — that happens at collect. */
    public function approve(DevicePairing $pairing): void
    {
        abort_if($pairing->status !== DevicePairing::PENDING_APPROVAL || $pairing->isExpired(), 422);
        $pairing->update(['status' => DevicePairing::APPROVED]);
    }

    /** Owner declines a claimed device (web). */
    public function reject(DevicePairing $pairing): void
    {
        abort_if($pairing->isExpired(), 410);
        $pairing->update(['status' => DevicePairing::REJECTED]);
    }

    /**
     * App polls with the code. Returns ['status' => 'pending'] until approved, then
     * mints + returns the Sanctum token EXACTLY ONCE and consumes the pairing.
     */
    public function collect(string $code, ?string $ip = null): array
    {
        $pairing = DevicePairing::query()->where('code_hash', $this->hash($code))->first();
        abort_if(
            $pairing === null || $pairing->isExpired()
                || in_array($pairing->status, [DevicePairing::REJECTED, DevicePairing::CONSUMED], true),
            410
        );

        if (in_array($pairing->status, [DevicePairing::PENDING_SCAN, DevicePairing::PENDING_APPROVAL], true)) {
            return ['status' => 'pending'];
        }

        // APPROVED → enforce the device cap (revoke the oldest tokens so this new
        // one keeps the user at the configured maximum), then mint once.
        $user = $pairing->user;
        $max = max(1, (int) config('devices.max', 3));
        $existing = $user->tokens()->orderBy('id')->pluck('id');
        $overflow = $existing->count() - ($max - 1);
        if ($overflow > 0) {
            $user->tokens()->whereIn('id', $existing->take($overflow))->delete();
        }

        $token = $user->createToken($pairing->device_name ?? 'device');
        // Record the paired device's IP for the web "Connected devices" list.
        if ($ip !== null) {
            $token->accessToken->forceFill(['ip' => $ip])->save();
        }
        $pairing->update(['status' => DevicePairing::CONSUMED, 'token_id' => $token->accessToken->getKey()]);

        return ['status' => 'approved', 'token' => $token->plainTextToken, 'user' => $pairing->user];
    }

    /** Drop expired and terminal rows. Returns the number deleted. */
    public function prune(): int
    {
        return DevicePairing::query()
            ->where('expires_at', '<', now())
            ->orWhereIn('status', [DevicePairing::CONSUMED, DevicePairing::REJECTED])
            ->delete();
    }

    private function require(string $code, string $expectedStatus): DevicePairing
    {
        $pairing = DevicePairing::query()->where('code_hash', $this->hash($code))->first();
        abort_if($pairing === null || $pairing->isExpired() || $pairing->status !== $expectedStatus, 410);

        return $pairing;
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
