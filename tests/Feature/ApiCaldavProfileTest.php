<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/account/caldav-profile downloads a standalone Apple CalDAV
 * .mobileconfig (device auth + module:calendar). Carries the username, never a
 * password; SSL/port are derived from the request.
 */
class ApiCaldavProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/account/caldav-profile')->assertUnauthorized();
    }

    public function test_returns_a_standalone_caldav_mobileconfig(): void
    {
        $user = User::factory()->create(['email' => 'cal@example.com']);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];

        $response = $this->get('/api/v1/account/caldav-profile', $headers);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
        $this->assertStringContainsString('attachment; filename="ledgerline-calendar.mobileconfig"', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('com.apple.caldav.account', $body);
        $this->assertStringNotContainsString('com.apple.carddav.account', $body); // standalone
        $this->assertStringContainsString('cal@example.com', $body);
        $this->assertStringNotContainsString('CalDAVPassword', $body);
    }
}
