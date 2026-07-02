<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
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
        $this->assertStringContainsString("object-src 'none'", $csp);
        // Alpine needs 'unsafe-eval'; inline scripts stay disallowed.
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }
}
