<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promote a `?_token=` query parameter to an `Authorization: Bearer` header when
 * no header is present. This lets the token-authenticated SPA load media/stream
 * endpoints (avatar, file raw/thumb, invoice PDF, logo) via <img>/<iframe>/<a>,
 * which cannot set request headers. Only fills in a missing header — never
 * overrides a real Authorization header. A Go API would do the same.
 */
class BearerFromQuery
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $token = $request->query('_token');
            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
