<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function admin(): array
    {
        $admin = User::factory()->admin()->create(['password' => 'supersecret12']);

        return [$admin, $admin->createToken('t', ['device'])->plainTextToken];
    }

    public function test_smtp_host_rejects_ssrf_target(): void
    {
        [, $token] = $this->admin();

        // Cloud-metadata / link-local literal must be refused (SafeHost), matching
        // the ntfy_url / webhook_url SSRF guards.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/notifications', [
                'smtp_host' => '169.254.169.254',
            ])->assertStatus(422)->assertJsonValidationErrors('smtp_host');

        $this->assertNull(AppSettings::current()->smtp_host);
    }

    public function test_smtp_host_accepts_public_host(): void
    {
        [, $token] = $this->admin();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/notifications', [
                'smtp_host' => 'smtp.example.com',
            ])->assertOk();

        $this->assertSame('smtp.example.com', AppSettings::current()->smtp_host);
    }
}
