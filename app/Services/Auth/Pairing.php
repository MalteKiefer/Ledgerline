<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AppSettings;
use App\Models\DevicePairing;
use App\Models\User;
use App\Support\DeviceAudit;
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

    /**
     * CLI codes are copied/pasted by hand (no QR), so they live for a tighter
     * 60-second window. The state machine is otherwise identical to the app's.
     */
    public const CLI_TTL_SECONDS = 60;

    /**
     * Create a fresh pairing for the (web-authenticated) user; returns the row +
     * the raw code. The lifetime defaults to the app's QR window but callers may
     * pass a shorter one (e.g. the copy/paste CLI flow).
     *
     * @return array{pairing: DevicePairing, code: string}
     */
    public function create(User $user, ?int $ttlSeconds = null): array
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
            'expires_at' => now()->addSeconds(max(1, $ttlSeconds ?? self::TTL_SECONDS)),
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
     *
     * @param  array{install_id?: ?string, app_version?: ?string, os_version?: ?string}  $client  Non-secret client-correlation fields reported by the pairing device.
     * @return array{status: 'pending'}|array{status: 'approved', token: string, user: User}
     */
    public function collect(string $code, ?string $ip = null, array $client = []): array
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

        // APPROVED → atomically claim the pairing (CAS from APPROVED to CONSUMED)
        // so two concurrent polls can never both mint a token and blow past the
        // device cap. Only the request that flips exactly one row proceeds.
        $claimed = DevicePairing::query()
            ->whereKey($pairing->id)
            ->where('status', DevicePairing::APPROVED)
            ->update(['status' => DevicePairing::CONSUMED]);
        if ($claimed !== 1) {
            // Lost the race — the concurrent winner already minted the token.
            return ['status' => 'pending'];
        }

        // Enforce the device cap (revoke the oldest tokens so this new one keeps
        // the user at the configured maximum), then mint once.
        $user = $pairing->user;
        // A consumed pairing always has its owning user; guard for the type system.
        abort_if($user === null, 410);
        // Admin-configurable cap (Security settings); null/0 falls back to config default.
        $configuredMax = AppSettings::current()->max_connected_devices ?: config('devices.max', 3);
        $max = max(1, is_numeric($configuredMax) ? (int) $configuredMax : 3);
        // LRU eviction: keep the most-recently-used devices. A token that never
        // contacted the API (last_used_at NULL) is evicted first, then the least
        // recently used, then the oldest by id. This never evicts an actively-used
        // device just because it was paired early (the old orderBy('id') bug).
        $existing = $user->tokens()
            ->orderByRaw('last_used_at is null desc')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->get();
        $overflow = $existing->count() - ($max - 1);
        if ($overflow > 0) {
            foreach ($existing->take($overflow) as $victim) {
                DeviceAudit::record($victim, 'device.evicted', ['reason' => 'cap', 'cap' => $max]);
                $victim->delete();
            }
        }

        // Scope the token to a named 'device' ability (not the '*' wildcard) so a
        // future ability check can constrain what a paired device may reach.
        // Set expires_at explicitly (= the Sanctum global lifetime) so the absolute
        // expiry is visible in the device list and auditable when it lapses, rather
        // than an invisible global cut-off (config/sanctum.php expiration).
        $ttlCfg = config('sanctum.expiration', 60 * 24 * 180);
        $ttl = is_numeric($ttlCfg) ? (int) $ttlCfg : 60 * 24 * 180;
        $expiresAt = $ttl > 0 ? now()->addMinutes($ttl) : null;
        $token = $user->createToken($pairing->device_name ?? 'device', ['device'], $expiresAt);
        // Record the paired device's IP + non-secret client-correlation fields for
        // the web "Connected devices" list and the audit trail.
        $fill = [];
        if ($ip !== null) {
            $fill['ip'] = $ip;
        }
        foreach (['install_id', 'app_version', 'os_version'] as $field) {
            $val = $client[$field] ?? null;
            if (is_string($val) && $val !== '') {
                $fill[$field] = mb_substr($val, 0, $field === 'install_id' ? 64 : 32);
            }
        }
        if ($fill !== []) {
            $token->accessToken->forceFill($fill)->save();
        }
        $pairing->forceFill(['token_id' => $token->accessToken->getKey()])->save();

        return ['status' => 'approved', 'token' => $token->plainTextToken, 'user' => $user];
    }

    /** Drop expired and terminal rows. Returns the number deleted. */
    public function prune(): int
    {
        $deleted = DevicePairing::query()
            ->where('expires_at', '<', now())
            ->orWhereIn('status', [DevicePairing::CONSUMED, DevicePairing::REJECTED])
            ->delete();

        return is_int($deleted) ? $deleted : 0;
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
