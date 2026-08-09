<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function admin(): array
    {
        $admin = User::factory()->admin()->create(['password' => 'supersecret12']);
        $token = $admin->createToken('t', ['device'])->plainTextToken;

        return [$admin, $token];
    }

    public function test_request_log_records_requests_and_admin_can_read(): void
    {
        [, $token] = $this->admin();
        // Any request populates the log (LogRequest terminable middleware).
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk();
        $this->assertGreaterThan(0, RequestLog::count());

        $r = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/admin/request-log');
        $r->assertOk()->assertJsonStructure(['data' => [['id', 'ip', 'method', 'path', 'status']], 'meta']);
    }

    public function test_request_log_is_admin_only(): void
    {
        $user = User::factory()->create(['password' => 'supersecret12']);
        $token = $user->createToken('t', ['device'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/admin/request-log')->assertForbidden();
    }

    public function test_admin_can_block_and_unblock_an_ip(): void
    {
        [, $token] = $this->admin();
        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/blocked-ips', ['cidr' => '203.0.113.7', 'reason' => 'abuse'])->assertCreated();
        $id = $create->json('id');
        $this->assertDatabaseHas('blocked_ips', ['cidr' => '203.0.113.7']);

        $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson("/api/v1/admin/blocked-ips/{$id}")->assertOk();
        $this->assertDatabaseMissing('blocked_ips', ['cidr' => '203.0.113.7']);
    }

    public function test_block_ip_rejects_malformed_input(): void
    {
        [, $token] = $this->admin();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/blocked-ips', ['cidr' => 'not-an-ip'])->assertStatus(422);
    }

    public function test_blocked_ip_is_refused_by_the_guard(): void
    {
        BlockedIp::create(['cidr' => '198.51.100.0/24']);
        Cache::forget(\App\Http\Middleware\BlockGuard::CACHE_KEY);
        // A request from within the blocked range → 403 (BlockGuard runs on web/api,
        // not on the framework health route /up).
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])->get('/login')->assertForbidden();
        // An address outside the range is served normally.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/login')->assertOk();
    }

    public function test_admin_can_block_a_user_and_it_kills_their_tokens(): void
    {
        [, $adminToken] = $this->admin();
        $victim = User::factory()->create(['password' => 'supersecret12']);
        $victim->createToken('v', ['device']);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson("/api/v1/admin/users/{$victim->id}/block")->assertOk();

        $this->assertNotNull($victim->fresh()?->blocked_at);
        $this->assertSame(0, $victim->fresh()?->tokens()->count());

        // A blocked user cannot log in.
        $this->postJson('/api/v1/auth/login', ['email' => $victim->email, 'password' => 'supersecret12'])->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson("/api/v1/admin/users/{$victim->id}/unblock")->assertOk();
        $this->assertNull($victim->fresh()?->blocked_at);
    }

    public function test_admin_cannot_block_themselves(): void
    {
        [$admin, $token] = $this->admin();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/users/{$admin->id}/block")->assertStatus(422);
    }

    public function test_cidr_matcher(): void
    {
        $this->assertTrue(BlockedIp::ipMatchesCidr('10.1.2.3', '10.0.0.0/8'));
        $this->assertFalse(BlockedIp::ipMatchesCidr('11.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(BlockedIp::ipMatchesCidr('1.2.3.4', '1.2.3.4'));
        $this->assertTrue(BlockedIp::ipMatchesCidr('1.2.3.4', '1.2.3.0/24'));
        $this->assertFalse(BlockedIp::ipMatchesCidr('1.2.4.4', '1.2.3.0/24'));
        $this->assertFalse(BlockedIp::ipMatchesCidr('::1', '10.0.0.0/8')); // v6 vs v4 no match
    }
}
