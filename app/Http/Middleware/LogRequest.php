<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verbose security access log: records EVERY web + API request (method, path,
 * status, client IP, user, user-agent, referer, duration) after the response,
 * so the admin security portal has a full request trail. Metadata only — never
 * request bodies. Best-effort: a logging failure must never affect the request.
 *
 * High-volume static/asset/health noise is skipped; everything else is kept and
 * pruned on a retention window (ops.request_log_retention_days).
 */
class LogRequest
{
    /** Path prefixes that are pure noise for a security trail. */
    private const SKIP_PREFIXES = ['build/', 'storage/', 'up', 'favicon.ico', 'robots.txt', '.well-known/'];

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('log_request_start', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $path = ltrim($request->path(), '/');
            foreach (self::SKIP_PREFIXES as $prefix) {
                if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                    return;
                }
            }

            $start = $request->attributes->get('log_request_start');
            $duration = is_float($start) ? (int) round((microtime(true) - $start) * 1000) : null;

            // request->ip() honors TrustedProxies, so behind the NetBird proxy this
            // is the real client IP (from X-Forwarded-For), not the overlay peer.
            RequestLog::create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
                'method' => $request->getMethod(),
                'path' => mb_substr('/'.$path.($request->getQueryString() !== null ? '?'.$request->getQueryString() : ''), 0, 2048),
                'status' => $response->getStatusCode(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
                'referer' => ($ref = $request->headers->get('referer')) !== null ? mb_substr($ref, 0, 2048) : null,
                'duration_ms' => $duration,
            ]);
        } catch (Throwable) {
            // Never let audit logging break a request.
        }
    }
}
