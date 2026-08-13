<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The device access trail (throttled usage log) and the 401/403 reason audit.
 */
class ApiAccessTrailTest extends TestCase
{
    use RefreshDatabase;

    private function deviceToken(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    public function test_authenticated_request_writes_a_throttled_access_trail(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $bearer = $this->deviceToken($user);

        $this->withHeaders(['Authorization' => 'Bearer '.$bearer])
            ->getJson('/api/v1/me')->assertOk();

        $rows = DeviceAccessLog::all();
        $this->assertCount(1, $rows);
        $this->assertSame('me', $rows->first()->route_group);
        $this->assertSame(200, $rows->first()->status);
        $this->assertSame($user->id, $rows->first()->user_id);

        // Throttled: a second hit within the minute does NOT add a row.
        $this->withHeaders(['Authorization' => 'Bearer '.$bearer])
            ->getJson('/api/v1/me')->assertOk();
        $this->assertSame(1, DeviceAccessLog::count());
    }

    public function test_expired_token_401_is_audited_with_reason(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $token = $user->createToken('old', ['device']);
        $token->accessToken->forceFill(['expires_at' => now()->subDay()])->save();

        $this->withHeaders(['Authorization' => 'Bearer '.$token->plainTextToken])
            ->getJson('/api/v1/me')->assertUnauthorized();

        $log = AuditLog::where('action', 'auth.unauthorized')->first();
        $this->assertNotNull($log);
        $this->assertSame('token_expired', $log->meta['reason']);
        $this->assertSame($token->accessToken->id, $log->meta['token_id']);
    }

    public function test_revoked_token_401_reason_is_token_revoked(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $token = $user->createToken('gone', ['device']);
        $plain = $token->plainTextToken;
        $token->accessToken->delete(); // token no longer in DB

        $this->withHeaders(['Authorization' => 'Bearer '.$plain])
            ->getJson('/api/v1/me')->assertUnauthorized();

        $this->assertSame('token_revoked', AuditLog::where('action', 'auth.unauthorized')->first()->meta['reason']);
    }

    public function test_no_bearer_401_is_not_audited(): void
    {
        Cache::flush();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame(0, AuditLog::where('action', 'auth.unauthorized')->count());
    }

    public function test_access_log_prune_respects_retention(): void
    {
        config(['ops.access_log_retention_days' => 30]);
        DeviceAccessLog::create(['token_id' => 1, 'route_group' => 'me', 'status' => 200, 'created_at' => now()->subDays(60)]);
        DeviceAccessLog::create(['token_id' => 1, 'route_group' => 'me', 'status' => 200, 'created_at' => now()->subDay()]);

        $this->artisan('device-access-log:prune')->assertSuccessful();

        $this->assertSame(1, DeviceAccessLog::count());
    }
}
