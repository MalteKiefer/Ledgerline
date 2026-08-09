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
 * 'unsafe-eval' is scoped to the legacy Alpine Blade pages only (Alpine evaluates
 * x-* expressions via the Function constructor); the Vue SPA — the primary UI,
 * precompiled by Vite — is served eval-free. See responseNeedsEval(). No inline
 * <script> or inline event handlers are emitted anywhere in the app, so
 * script-src omits 'unsafe-inline'
 * (the sole inline script — the theme bootstrap — is allowed via its exact
 * sha256 hash). This is a defence-in-depth policy for the application shell
 * only: script-src still forbids loading scripts from other origins. The real
 * untrusted-content surface — user-uploaded bytes (receipts / logos / avatars /
 * PDFs) — is streamed on its own responses with a strict
 * `default-src 'none'; sandbox` CSP, which this middleware detects and never
 * clobbers (see the sandbox check below).
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

        // Strip stack-fingerprinting headers the base image / PHP-FPM may add.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Framing policy is driven by security.frame_ancestors (CSP frame-ancestors
        // below is authoritative). X-Frame-Options can only express deny/sameorigin,
        // not an allowlist: emit DENY for 'none', SAMEORIGIN for 'self', and drop XFO
        // for any broader allowlist (let frame-ancestors rule). A literal '*' never
        // reaches here on a TLS box (frameAncestors() downgrades it to 'self').
        $frameAncestors = $this->frameAncestors();
        if ($frameAncestors === "'none'") {
            $response->headers->set('X-Frame-Options', 'DENY');
        } elseif ($frameAncestors === "'self'") {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // No resource of this single-origin app is meant to be embedded/read by
        // another origin. Blocks cross-site hotlinking/embedding of the now-
        // plaintext user blobs (photos/files/PDFs) that COOP does not cover.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        // Deny access to powerful browser features the app never uses. (The
        // deprecated FLoC `interest-cohort` token was dropped — Chrome removed
        // FLoC and now logs "Unrecognized feature" for it.)
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), camera=(), microphone=(), payment=(), usb=(), '
            .'accelerometer=(), gyroscope=(), magnetometer=(), autoplay=(), '
            .'display-capture=(), fullscreen=(self), browsing-topics=(), '
            .'serial=(), midi=(), hid=(), bluetooth=(), idle-detection=()'
        );

        // Pin HTTPS only when the deployment is actually served over TLS
        // (secure session cookies configured); never on a plaintext local box.
        // Include preload so the domain can be added to the browser HSTS list,
        // closing the first-visit downgrade window on the passphrase page.
        if (config('session.secure')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
            // COOP is only honoured on a trustworthy (HTTPS) origin — browsers
            // ignore it on plaintext HTTP and log a console warning. Emit it only
            // when the deployment is served over TLS (same signal as HSTS) so a
            // plaintext LAN box stays quiet; it isolates this browsing context
            // group from cross-origin openers/popups on real HTTPS deployments.
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
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
                implode('; ', $this->appPolicy($this->responseNeedsEval($response)))
            );
        }

        return $response;
    }

    /**
     * Whether this response is a legacy Alpine Blade page that needs 'unsafe-eval'.
     *
     * The primary UI is the Vue SPA (spa.blade.php), precompiled by Vite → no eval.
     * Only the legacy Alpine-backed Blade pages (guest/app layouts: e.g. the invite
     * page) evaluate x-* expressions via the Function constructor. The eval-free SPA
     * shell is the ONLY view containing `<div id="app">`, so scope 'unsafe-eval' to
     * pages that are NOT the SPA shell. Non-HTML responses (JSON API, streamed blobs)
     * never need eval and get the tighter policy.
     */
    private function responseNeedsEval(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($type, 'text/html')) {
            return false;
        }

        // Streamed/binary responses (StreamedResponse/BinaryFileResponse) return
        // false from getContent() — no in-repo inline eval surface, treat as SPA.
        $body = $response->getContent();
        if (! is_string($body)) {
            return false;
        }

        return ! str_contains($body, 'id="app"');
    }

    /**
     * The CSP `frame-ancestors` source list (who may embed this app in a frame).
     * Defaults to `'self'` (same-origin framing only; XFO SAMEORIGIN also emitted).
     * Set FRAME_ANCESTORS to `'none'` to refuse all framing, or to a source list
     * (e.g. "'self' https://dashboard.example") for specific embedders. Trimmed;
     * empty falls back to 'self'.
     *
     * A literal `*` (framing by ANY origin) is a clickjacking exposure on an
     * internet-facing deployment, so it is REFUSED when the box is served over
     * TLS (FORCE_HTTPS or secure cookies on) — it is downgraded to 'self'. This
     * mirrors the TRUSTED_PROXIES='*' refusal in bootstrap/app.php (never trust a
     * blanket wildcard on a public host).
     */
    private function frameAncestors(): string
    {
        $v = config('security.frame_ancestors', "'self'");
        $v = is_string($v) ? trim($v) : '';

        if ($v === '') {
            return "'self'";
        }

        // Fail safe: never allow universal framing on a TLS / internet-facing box.
        if ($v === '*' && (config('app.force_https') || config('session.secure'))) {
            return "'self'";
        }

        return $v;
    }

    /**
     * Defence-in-depth CSP for the authenticated application shell.
     *
     * @param  bool  $allowEval  include 'unsafe-eval' (only for legacy Alpine pages)
     * @return list<string>
     */
    private function appPolicy(bool $allowEval): array
    {
        return [
            "default-src 'self'",
            "base-uri 'none'",
            // 'self' + blob: so the in-app PDF viewer can render a file (an
            // <object> pointing at a client-generated blob: URL); no remote
            // plugin content is allowed.
            "object-src 'self' blob:",
            'frame-ancestors '.$this->frameAncestors(),
            "form-action 'self'",
            // The only inline script is the theme bootstrap (allowed via its
            // exact hash), so 'unsafe-inline' stays dropped. 'unsafe-eval' is
            // added ONLY for legacy Alpine Blade pages (they evaluate x-* via the
            // Function constructor); the Vue SPA — precompiled by Vite — runs
            // eval-free. Cross-origin scripts stay forbidden either way.
            $allowEval
                ? "script-src 'self' 'unsafe-eval' ".ThemeBootstrap::cspHash()
                : "script-src 'self' ".ThemeBootstrap::cspHash(),
            "style-src 'self' 'unsafe-inline'",
            // App content is same-origin only (blobs -> blob: URLs, avatars, QR
            // as data:). No remote image host is allowed — contacts now uses an
            // OSM *link*, not tiles — so this stays tighter than a blanket 'https:'
            // and closes the "any https host" exfil channel the backstop contains.
            "img-src 'self' data: blob:",
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
