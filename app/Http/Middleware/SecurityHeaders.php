<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ThemeBootstrap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * App-wide security headers, incl. a Content-Security-Policy that acts as a
 * defence-in-depth backstop: even if untrusted content ever reached the app
 * origin, it could not load remote scripts, be framed, or post elsewhere.
 *
 * 'unsafe-eval' is required by Alpine.js (it evaluates x-* expressions via the
 * Function constructor). No inline <script> or inline event handlers are
 * emitted anywhere in the app, so script-src omits 'unsafe-inline'. This is a
 * defence-in-depth policy for the application shell only: script-src still
 * forbids loading scripts from other origins, and the real untrusted-content
 * surface — email bodies — renders in separate sandboxed iframes with their
 * own strict, script-less CSP.
 *
 * The CSP is skipped in local development so the Vite dev server / HMR (which
 * injects an inline client and connects to its own origin) keeps working.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Deny access to powerful browser features the app never uses.
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()'
        );

        // Pin HTTPS only when the deployment is actually served over TLS
        // (secure session cookies configured); never on a plaintext local box.
        if (config('session.secure')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! app()->environment('local')) {
            $response->headers->set(
                'Content-Security-Policy',
                implode('; ', $this->appPolicy())
            );
        }

        return $response;
    }

    /**
     * Defence-in-depth CSP for the authenticated application shell.
     *
     * @return list<string>
     */
    private function appPolicy(): array
    {
        return [
            "default-src 'self'",
            "base-uri 'none'",
            // 'self' + blob: so the in-app PDF viewer can render a file (an
            // <object> pointing at a client-generated blob: URL); no remote
            // plugin content is allowed.
            "object-src 'self' blob:",
            "frame-ancestors 'none'",
            "form-action 'self'",
            // The only inline script is the theme bootstrap (allowed via its
            // exact hash), so 'unsafe-inline' stays dropped. 'unsafe-eval'
            // remains because stock Alpine evaluates x-* expressions via the
            // Function constructor; cross-origin scripts stay forbidden.
            "script-src 'self' 'unsafe-eval' ".ThemeBootstrap::cspHash(),
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            // blob: for client-generated URLs; https: so inline video/audio can
            // stream from the signed S3/object-storage URL (mirrors img-src).
            "media-src 'self' blob: https:",
            "connect-src 'self'",
            // blob: so the in-app PDF viewer works: some browsers render an
            // <object>/<embed> PDF through an internal frame from a
            // client-generated blob: URL.
            "frame-src 'self' blob:",
        ];
    }
}
