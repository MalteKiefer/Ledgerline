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
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Isolate this browsing context group from cross-origin openers/popups
        // (mitigates cross-window attacks; harmless for a same-origin SPA).
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // No resource of this single-origin app is meant to be embedded/read by
        // another origin. Blocks cross-site hotlinking/embedding of the now-
        // plaintext user blobs (photos/files/PDFs) that COOP does not cover.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        // Deny access to powerful browser features the app never uses.
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()'
        );

        // Pin HTTPS only when the deployment is actually served over TLS
        // (secure session cookies configured); never on a plaintext local box.
        // Include preload so the domain can be added to the browser HSTS list,
        // closing the first-visit downgrade window on the passphrase page.
        if (config('session.secure')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        // A route that streams untrusted bytes (file/blob downloads) sets its own
        // strict `default-src 'none'; sandbox` CSP — never clobber that with the
        // app-shell policy, or a plaintext user-uploaded HTML served inline would
        // execute same-origin. Only apply the shell policy when the response has
        // not already opted into the sandbox.
        $existing = (string) $response->headers->get('Content-Security-Policy', '');
        $isSandboxed = str_contains($existing, 'sandbox');
        if (! app()->environment('local') && ! $isSandboxed) {
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
            // App content is same-origin (encrypted /raw blobs -> blob: URLs,
            // avatars, QR as data:). The ONLY remote images are Leaflet OSM map
            // tiles, so scope to that host instead of a blanket 'https:' — this
            // closes the "any https host" exfil channel the backstop is meant to
            // contain while keeping the maps working.
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
            "font-src 'self' data:",
            // blob: for client-decrypted video/audio; no remote media origin.
            "media-src 'self' blob:",
            "connect-src 'self'",
            // The gallery's duplicate scan runs its O(n^2) comparison in a
            // same-origin Web Worker (bundled by Vite); allow only 'self'.
            "worker-src 'self'",
            // blob: so the in-app PDF viewer works: some browsers render an
            // <object>/<embed> PDF through an internal frame from a
            // client-generated blob: URL.
            "frame-src 'self' blob:",
        ];
    }
}
