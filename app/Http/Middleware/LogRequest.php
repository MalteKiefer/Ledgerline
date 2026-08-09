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

    /**
     * Query-parameter names whose VALUE is a credential/token and must never be
     * persisted. The SPA appends `?_token=<bearer>` to media URLs, public shares
     * use `?grant=<hmac>`, and password/secret params may appear on auth flows —
     * all would otherwise sit in cleartext in this admin-readable, exportable,
     * backup-able PII store. Matched case-insensitively; the KEY (and any other,
     * non-secret params) is kept for diagnostics, only the value is scrubbed.
     */
    private const SECRET_QUERY_KEYS = ['_token', 'token', 'grant', 'passphrase', 'password', 'secret', 'api_key', 'key'];

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

            $query = $request->getQueryString();
            $full = self::sanitizeUrl('/'.$path.($query !== null ? '?'.$query : ''));
            $ref = $request->headers->get('referer');

            // request->ip() honors TrustedProxies, so behind the NetBird proxy this
            // is the real client IP (from X-Forwarded-For), not the overlay peer.
            RequestLog::create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
                'method' => $request->getMethod(),
                // Redact bearer/grant/invite tokens BEFORE truncating so a token can
                // never survive (fully or partially) in this store — see F1.
                'path' => mb_substr($full, 0, 2048),
                'status' => $response->getStatusCode(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
                'referer' => $ref !== null ? mb_substr(self::sanitizeUrl($ref), 0, 2048) : null,
                'duration_ms' => $duration,
            ]);
        } catch (Throwable) {
            // Never let audit logging break a request.
        }
    }

    /**
     * Scrub credentials from a stored path/URL: mask invite/reset PATH tokens and
     * redact the VALUE of any denylisted query parameter. The non-secret path and
     * params are preserved so the trail stays useful for diagnostics.
     */
    private static function sanitizeUrl(string $value): string
    {
        $q = strpos($value, '?');
        if ($q === false) {
            return self::maskPathTokens($value);
        }

        return self::maskPathTokens(substr($value, 0, $q)).'?'.self::redactQuery(substr($value, $q + 1));
    }

    /**
     * The invite / password-reset consumption route carries a single-use token in
     * the URL PATH (invite/{invite}/{token}). Replace that trailing token segment
     * so it is never persisted.
     */
    private static function maskPathTokens(string $path): string
    {
        return (string) preg_replace('~(/invite/[^/]+/)[^/?]+~', '$1[redacted]', $path);
    }

    /** Replace the value of every denylisted query key with [redacted]; keep the rest. */
    private static function redactQuery(string $query): string
    {
        $pairs = explode('&', $query);
        foreach ($pairs as $i => $pair) {
            $eq = strpos($pair, '=');
            $key = $eq === false ? $pair : substr($pair, 0, $eq);
            if (in_array(strtolower(rawurldecode($key)), self::SECRET_QUERY_KEYS, true)) {
                $pairs[$i] = $key.'=[redacted]';
            }
        }

        return implode('&', $pairs);
    }
}
