<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    public function test_requires_admin(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/dashboard', $this->bearer(User::factory()->create()))->assertForbidden();
    }

    public function test_admin_sees_status_resources_and_counts(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $res = $this->getJson('/api/v1/admin/dashboard', $this->bearer($admin))->assertOk();
        $res->assertJsonStructure([
            'versions' => ['app', 'php', 'laravel'],
            'health' => ['database', 'cache', 'queue_driver'],
            'resources' => ['disk' => ['free', 'total'], 'storage' => ['files', 'gallery', 'database', 'total']],
            'queue' => ['pending', 'failed'],
            'scheduler' => ['tasks'],
            'errors' => ['unresolved', 'total'],
            'backup' => ['lastSuccessAt'],
            'counts' => ['users', 'admins', 'web_sessions', 'device_tokens', 'blocked_ips'],
        ]);
        $res->assertJsonPath('health.database', 'up');
        $res->assertJsonPath('counts.users', 1);
        $res->assertJsonPath('counts.admins', 1);
    }
}
