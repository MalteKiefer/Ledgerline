<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drop-in replacement for the framework `throttle` middleware that scales every
 * inline numeric limit (`throttle:60,1`, …) by `config('app.throttle_multiplier')`.
 *
 * On a private, trusted, non-internet-facing deployment the per-route limits are
 * needlessly tight (e.g. the Finance page loads /paperless/terms on init and can
 * trip a 60/min cap). Set THROTTLE_MULTIPLIER (env) high there; the default of 1
 * leaves the shipped limits unchanged for a public deployment. Named limiters
 * (`throttle:fortify`) are passed through untouched.
 */
class ScaledThrottleRequests extends ThrottleRequests
{
    /**
     * @param  int|string  $maxAttempts
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if (is_numeric($maxAttempts)) {
            $mult = config('app.throttle_multiplier', 1);
            $mult = is_numeric($mult) && (int) $mult > 1 ? (int) $mult : 1;
            $maxAttempts = (int) $maxAttempts * $mult;
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
