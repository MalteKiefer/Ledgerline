<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiDockerControlTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    public function test_requires_admin(): void
    {
        $this->getJson('/api/v1/admin/docker/containers')->assertUnauthorized();
        $this->getJson('/api/v1/admin/docker/containers', $this->bearer(User::factory()->create()))->assertForbidden();
    }

    public function test_reports_unconfigured_when_no_token(): void
    {
        config(['docker.agent_token' => '']);
        $this->getJson('/api/v1/admin/docker/containers', $this->bearer($this->admin()))
            ->assertOk()->assertJsonPath('configured', false);
    }

    public function test_lists_services_via_agent(): void
    {
        config(['docker.agent_url' => 'http://agent:9000', 'docker.agent_token' => 'secret']);
        Http::fake(['agent:9000/list' => Http::response(['services' => [
            ['service' => 'app', 'state' => 'running', 'status' => 'Up', 'image' => 'ghcr.io/x:1'],
        ]])]);

        $this->getJson('/api/v1/admin/docker/containers', $this->bearer($this->admin()))
            ->assertOk()->assertJsonPath('reachable', true)->assertJsonPath('services.0.service', 'app');
    }

    public function test_action_validates_and_proxies(): void
    {
        config(['docker.agent_url' => 'http://agent:9000', 'docker.agent_token' => 'secret']);
        Http::fake(['agent:9000/action' => Http::response(['ok' => true, 'action' => 'restart', 'service' => 'ml'])]);

        // Bad action rejected.
        $this->postJson('/api/v1/admin/docker/action', ['service' => 'ml', 'action' => 'exec'], $this->bearer($this->admin()))
            ->assertStatus(422);
        // Allowlisted action proxied.
        $this->postJson('/api/v1/admin/docker/action', ['service' => 'ml', 'action' => 'restart'], $this->bearer($this->admin()))
            ->assertOk()->assertJsonPath('ok', true);
    }

    public function test_action_503_when_unconfigured(): void
    {
        config(['docker.agent_token' => '']);
        $this->postJson('/api/v1/admin/docker/action', ['service' => 'ml', 'action' => 'restart'], $this->bearer($this->admin()))
            ->assertStatus(503);
    }
}
