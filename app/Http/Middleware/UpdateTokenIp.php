<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DeviceAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request device-token guard for the bearer-authenticated API. It:
 *  - enforces the remote-wipe kill switch: once the owner flags a token and the
 *    grace window has elapsed, the token is hard-revoked on next contact (during
 *    grace the /me + heartbeat flag lets the client self-erase first);
 *  - records the request IP so the web "Connected devices" list shows where a
 *    device was last seen (a token used from an unexpected IP is a theft signal).
 * Idle + absolute expiry are handled out-of-band (PruneDeviceTokens + Sanctum's
 * own expiration) because Sanctum refreshes last_used_at during auth, before this
 * middleware sees it. Writes only when something actually changed.
 */
class UpdateTokenIp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            // Remote wipe → real revocation once the self-erase grace has passed.
            // wipe_requested_at is an app-added column Sanctum doesn't cast, so
            // parse it explicitly.
            $graceMinutes = config('devices.wipe_grace_minutes', 15);
            $graceMinutes = is_numeric($graceMinutes) ? (int) $graceMinutes : 15;
            if ($token->wipe_requested_at !== null
                && Carbon::parse($token->wipe_requested_at)->lte(now()->subMinutes($graceMinutes))) {
                DeviceAudit::record($token, 'device.wipe_finalized', [
                    'wipe_requested_at' => Carbon::parse((string) $token->wipe_requested_at)->toIso8601String(),
                    'grace_minutes' => $graceMinutes,
                    'enforced_by' => 'request',
                ]);
                $token->delete();
                abort(401, 'Device wiped.');
            }
            // Record an IP change as its own event (a token hopping IPs is a theft
            // signal), then persist the new IP for the device list.
            if ($token->ip !== $request->ip()) {
                if ($token->ip !== null && $token->ip !== '') {
                    DeviceAudit::record($token, 'device.ip_changed', ['old_ip' => $token->ip, 'new_ip' => $request->ip()]);
                }
                $token->forceFill(['ip' => $request->ip()])->save();
            }
        }

        return $next($request);
    }
}
