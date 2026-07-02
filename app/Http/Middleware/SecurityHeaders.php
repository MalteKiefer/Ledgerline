<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * App-wide security headers, incl. a Content-Security-Policy that acts as a
 * defence-in-depth backstop: even if untrusted content ever reached the app
 * origin, it could not load remote scripts, be framed, or post elsewhere.
 *
 * 'unsafe-eval' is required by Alpine.js (it evaluates x-* expressions via the
 * Function constructor). 'unsafe-inline' is required for inline <script> emitted
 * outside our Blade templates (the Vite/module bootstrap and any script the
 * hosting platform injects into the HTML), which cannot carry a nonce we
 * control. This is a defence-in-depth policy for the application shell only:
 * script-src still forbids loading scripts from other origins, and the real
 * untrusted-content surface — email bodies — renders in separate sandboxed
 * iframes with their own strict, script-less CSP.
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

        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'none'",
                // 'self' + blob: so the in-app PDF viewer can render a decrypted
                // file (an <object> pointing at a client-generated blob: URL); no
                // remote plugin content is allowed.
                "object-src 'self' blob:",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-src 'self'",
            ]));
        }

        return $response;
    }
}
