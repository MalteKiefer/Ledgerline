<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/account/carddav-profile downloads the Apple CardDAV .mobileconfig
 * (device auth + module:contacts). Carries the username, never a password.
 */
class ApiCarddavProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/account/carddav-profile')->assertUnauthorized();
    }

    public function test_returns_the_mobileconfig_attachment_for_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'dav@example.com']);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];

        $response = $this->get('/api/v1/account/carddav-profile', $headers);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
        $this->assertStringContainsString('attachment; filename="ledgerline-sync.mobileconfig"', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent();
        $this->assertIsString($body);
        // The combined profile provisions BOTH CardDAV (contacts) and CalDAV (calendar).
        $this->assertStringContainsString('com.apple.carddav.account', $body);
        $this->assertStringContainsString('com.apple.caldav.account', $body);
        $this->assertStringContainsString('dav@example.com', $body);
        // No password is ever embedded.
        $this->assertStringNotContainsString('CardDAVPassword', $body);
        $this->assertStringNotContainsString('CalDAVPassword', $body);
    }
}
