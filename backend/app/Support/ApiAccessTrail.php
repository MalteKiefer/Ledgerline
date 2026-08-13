<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\DeviceAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Records API access for device tokens without flooding the store:
 *  - a throttled per-device usage trail (device_access_log), at most one row per
 *    token per minute, with the route coarsened to a GROUP (never the full path);
 *  - a throttled `auth.unauthorized` audit entry with a reason code when a token
 *    was PRESENTED but rejected (expired / revoked / wiped / ability denied) —
 *    the diagnostic signal for "my device keeps getting 401".
 *
 * Metadata only: never the token value, never request bodies.
 */
final class ApiAccessTrail
{
    /** Coarse route group for a request path, e.g. api/v1/files/... → "files". */
    public static function routeGroup(Request $request): string
    {
        $segments = explode('/', trim($request->path(), '/'));
        // api / v1 / <group> / …
        $group = $segments[2] ?? ($segments[0] ?? '');

        return $group !== '' ? mb_substr($group, 0, 32) : 'root';
    }

    /**
     * Throttled usage-trail write for an authenticated device token. Called after
     * the response is known so the status is real. At most one row per token per
     * minute; the rest only refresh Sanctum's last_used_at (done elsewhere).
     */
    public static function trail(PersonalAccessToken $token, Request $request, int $status): void
    {
        try {
            $id = is_numeric($token->getKey()) ? (int) $token->getKey() : 0;
            if (! Cache::add('dal:'.$id, 1, 60)) {
                return; // already logged this token within the last minute
            }
            DeviceAccessLog::create([
                'token_id' => $id,
                'user_id' => is_numeric($token->tokenable_id) ? (int) $token->tokenable_id : null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'route_group' => self::routeGroup($request),
                'status' => $status,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Observational — must never break the request.
        }
    }

    /**
     * Throttled audit of a rejected API request. Only fires when a bearer token
     * WAS presented (so we skip the noise of anonymous/web 401s) and derives the
     * reason from the token's DB state. One entry per token/ip+reason per minute.
     */
    public static function unauthorized(Request $request, int $status): void
    {
        try {
            $bearer = $request->bearerToken();
            if ($bearer === null || $bearer === '') {
                return; // no token presented — not a device-diagnostic 401
            }

            $token = PersonalAccessToken::findToken($bearer);
            [$reason, $tokenId, $userId] = self::classify($token, $status);

            $throttleKey = 'auth401:'.($tokenId ?? $request->ip() ?? 'anon').':'.$reason;
            if (! Cache::add($throttleKey, 1, 60)) {
                return;
            }

            AuditLog::record('auth.unauthorized', null, array_filter([
                'reason' => $reason,
                'route_group' => self::routeGroup($request),
                'status' => $status,
                'token_id' => $tokenId,
            ], static fn ($v): bool => $v !== null), $userId);
        } catch (\Throwable) {
            // Never let diagnostics break error rendering.
        }
    }

    /**
     * Derive a reason code + ids from the presented token's state.
     *
     * @return array{0: string, 1: ?int, 2: ?int} [reason, token_id, user_id]
     */
    private static function classify(?PersonalAccessToken $token, int $status): array
    {
        if ($token === null) {
            return ['token_revoked', null, null]; // presented, but no longer in the DB
        }
        $tokenId = is_numeric($token->getKey()) ? (int) $token->getKey() : null;
        $userId = is_numeric($token->tokenable_id) ? (int) $token->tokenable_id : null;

        if ($token->wipe_requested_at !== null) {
            return ['token_wiped', $tokenId, $userId];
        }
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return ['token_expired', $tokenId, $userId];
        }
        if ($status === 403) {
            return ['ability_denied', $tokenId, $userId];
        }

        return ['unauthenticated', $tokenId, $userId];
    }
}
