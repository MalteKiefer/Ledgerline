<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NoteShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_hardening_headers_are_always_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_is_sent_outside_local_and_locks_down_the_shell(): void
    {
        // The test environment is not 'local', so the CSP is applied.
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        // object-src allows blob: (in-app PDF viewer) but no remote plugins.
        $this->assertStringContainsString("object-src 'self' blob:", $csp);
        // Alpine needs 'unsafe-eval'; there are no inline scripts so
        // 'unsafe-inline' is gone from script-src. Cross-origin scripts stay
        // forbidden ('self' only).
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
        $scriptSrc = collect(explode('; ', $csp))->first(fn ($d) => str_starts_with($d, 'script-src'));
        $this->assertStringNotContainsString("'unsafe-inline'", (string) $scriptSrc);
        $this->assertStringNotContainsString('script-src https:', $csp);
    }

    public function test_public_share_pages_get_a_script_less_csp(): void
    {
        $share = NoteShare::create(['title' => 'x', 'content' => 'y', 'expires_at' => now()->addDay()]);

        $response = $this->get(route('shares.show', $share));
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_hsts_is_sent_only_when_secure_cookies_are_configured(): void
    {
        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));

        config(['session.secure' => true]);
        $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
