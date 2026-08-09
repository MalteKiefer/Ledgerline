<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_hardening_headers_are_always_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        // Default frame policy is 'self' → SAMEORIGIN (same-origin framing only).
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Stack-fingerprinting headers are stripped.
        $this->assertNull($response->headers->get('X-Powered-By'));
        $this->assertNull($response->headers->get('Server'));
        // Permissions-Policy denies the unused powerful features.
        $pp = (string) $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $pp);
        $this->assertStringContainsString('camera=()', $pp);
        $this->assertStringContainsString('display-capture=()', $pp);
    }

    public function test_csp_default_frame_ancestors_is_self(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'self' blob:", $csp);
    }

    public function test_frame_ancestors_none_emits_deny(): void
    {
        config(['security.frame_ancestors' => "'none'"]);

        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_wildcard_frame_ancestors_is_refused_under_tls(): void
    {
        // A literal '*' (framing by ANY origin) is a clickjacking exposure on an
        // internet-facing box → downgraded to 'self' when served over TLS.
        config(['security.frame_ancestors' => '*', 'session.secure' => true]);

        $response = $this->get('/');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString('frame-ancestors *', $csp);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_spa_shell_is_served_without_unsafe_eval(): void
    {
        // The '/' route serves the eval-free Vue SPA shell (contains id="app").
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $scriptSrc = collect(explode('; ', $csp))->first(fn ($d) => str_starts_with($d, 'script-src'));
        $this->assertStringContainsString("script-src 'self'", (string) $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-eval'", (string) $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", (string) $scriptSrc);
    }

    public function test_legacy_alpine_html_keeps_unsafe_eval_but_spa_does_not(): void
    {
        $mw = new SecurityHeaders;

        // A non-SPA HTML page (legacy Alpine Blade, no id="app") keeps 'unsafe-eval'.
        $alpine = $mw->handle(Request::create('/invite/1/tok'), function () {
            return new Response('<html><body x-data></body></html>', 200, ['Content-Type' => 'text/html']);
        });
        $this->assertStringContainsString("'unsafe-eval'", (string) $alpine->headers->get('Content-Security-Policy'));

        // The SPA shell (id="app") is served eval-free.
        $spa = $mw->handle(Request::create('/'), function () {
            return new Response('<html><body><div id="app"></div></body></html>', 200, ['Content-Type' => 'text/html']);
        });
        $spaScript = collect(explode('; ', (string) $spa->headers->get('Content-Security-Policy')))
            ->first(fn ($d) => str_starts_with($d, 'script-src'));
        $this->assertStringNotContainsString("'unsafe-eval'", (string) $spaScript);
    }

    public function test_hsts_is_sent_only_when_secure_cookies_are_configured(): void
    {
        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));

        config(['session.secure' => true]);
        $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
    }
}
