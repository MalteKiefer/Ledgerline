<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record the IP each bearer request comes from on the token itself, so the web
 * "Connected devices" list shows where a device was last seen — a token used
 * from an unexpected IP is a theft signal. Only writes when the IP changed
 * (Sanctum already refreshes last_used_at every request), so it costs a DB write
 * only on an actual IP change, not on every call.
 */
class UpdateTokenIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->ip !== $request->ip()) {
            $token->forceFill(['ip' => $request->ip()])->save();
        }

        return $next($request);
    }
}
