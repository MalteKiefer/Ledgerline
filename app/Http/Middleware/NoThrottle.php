<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No-op replacement for the framework `throttle` middleware.
 *
 * Rate limiting is deliberately DISABLED app-wide (Owner decision, 2026-08-08):
 * Ledgerline runs on a private two-user home LAN, FDE-encrypted and not reachable
 * from the internet, so the per-route/auth rate limits added friction without a
 * threat to defend against. This middleware accepts and ignores every `throttle:…`
 * argument (both inline numeric limits and named limiters), so the existing
 * `throttle:` route declarations keep working as plain pass-throughs and no named
 * rate limiter ever needs to be resolved. See the Security register for the full
 * rationale + compensation (TLS/2FA/network isolation carry the security).
 */
class NoThrottle
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '', string $decayMinutes = '', string $prefix = ''): Response
    {
        return $next($request);
    }
}
