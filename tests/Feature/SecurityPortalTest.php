<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\BlockGuard;
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
        Cache::forget(BlockGuard::CACHE_KEY);
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

    public function test_request_log_redacts_bearer_grant_and_invite_tokens(): void
    {
        [, $token] = $this->admin();

        // Media/stream URLs carry the bearer as ?_token=, shares as ?grant=.
        // These must NEVER be persisted verbatim into the request-log PII store.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me?_token=leakedbearer123&grant=grantval456&api_key=akv789&x=1')
            ->assertOk();

        $row = RequestLog::query()->where('path', 'like', '%/api/v1/me%')->latest('id')->first();
        $this->assertNotNull($row);
        $path = (string) $row->path;
        $this->assertStringContainsString('_token=[redacted]', $path);
        $this->assertStringContainsString('grant=[redacted]', $path);
        $this->assertStringContainsString('api_key=[redacted]', $path);
        // Non-secret params are kept for diagnostics.
        $this->assertStringContainsString('x=1', $path);
        // No secret value survives anywhere in the stored path.
        $this->assertStringNotContainsString('leakedbearer123', $path);
        $this->assertStringNotContainsString('grantval456', $path);
        $this->assertStringNotContainsString('akv789', $path);

        // Invite/reset consumption carries a single-use token in the URL PATH.
        $this->getJson('/api/v1/invite/1/supersecretinvitetoken')->assertStatus(404);
        $invite = RequestLog::query()->where('path', 'like', '%/invite/%')->latest('id')->first();
        $this->assertNotNull($invite);
        $this->assertStringContainsString('/invite/1/[redacted]', (string) $invite->path);
        $this->assertStringNotContainsString('supersecretinvitetoken', (string) $invite->path);
    }

    public function test_request_log_redacts_tokens_from_referer(): void
    {
        [, $token] = $this->admin();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('referer', 'https://home.example/invite/1/reftoken999?_token=refbearer000')
            ->getJson('/api/v1/me')->assertOk();

        $row = RequestLog::query()->whereNotNull('referer')->latest('id')->first();
        $this->assertNotNull($row);
        $ref = (string) $row->referer;
        $this->assertStringContainsString('/invite/1/[redacted]', $ref);
        $this->assertStringContainsString('_token=[redacted]', $ref);
        $this->assertStringNotContainsString('reftoken999', $ref);
        $this->assertStringNotContainsString('refbearer000', $ref);
    }

    public function test_already_authenticated_blocked_user_is_forbidden_on_next_request(): void
    {
        $user = User::factory()->create(['password' => 'supersecret12']);
        $token = $user->createToken('t', ['device'])->plainTextToken;

        // The account is blocked but this token still exists (defense-in-depth:
        // BlockGuard must refuse the very next request, not only rely on the
        // one-shot token teardown at block time).
        $user->forceFill(['blocked_at' => now()])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')->assertStatus(403);
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
