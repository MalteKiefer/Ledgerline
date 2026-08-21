<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CheckServerReachability;
use App\Models\AppNotification;
use App\Models\Server;
use App\Models\ServerCheck;
use App\Models\User;
use App\Services\Servers\ReachabilityChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerReachabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_always_checks_the_ssh_port_alongside_the_configured_ones(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user, [['port' => 443, 'label' => 'https']]);

        // The port list is the contract between the job and the checker, so
        // assert on it rather than on whatever the network happens to do.
        $checker = new class extends ReachabilityChecker
        {
            /** @var list<int> */
            public array $seen = [];

            public function check(string $host, array $ports): array
            {
                $this->seen = array_values($ports);

                return [];
            }
        };

        (new CheckServerReachability($server->id))->handle($checker);

        $this->assertSame([2222, 443], $checker->seen, 'the SSH port must be checked whether or not it was listed');
    }

    #[Test]
    public function it_records_every_result(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $checker = $this->fakeChecker([
            ['kind' => 'icmp', 'port' => null, 'ok' => true, 'latency_ms' => 12, 'error' => null],
            ['kind' => 'tcp', 'port' => 2222, 'ok' => true, 'latency_ms' => 30, 'error' => null],
        ]);

        (new CheckServerReachability($server->id))->handle($checker);

        $this->assertSame(2, ServerCheck::query()->where('server_id', $server->id)->count());
        $icmp = ServerCheck::query()->where('kind', 'icmp')->firstOrFail();
        $this->assertTrue($icmp->ok);
        $this->assertSame(12, $icmp->latency_ms);
        $this->assertNull($icmp->port);
    }

    #[Test]
    public function a_single_failure_does_not_notify_but_a_sustained_one_does(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $down = $this->fakeChecker([['kind' => 'tcp', 'port' => 2222, 'ok' => false, 'latency_ms' => null, 'error' => 'timeout']]);

        // One dropped packet is not an outage — nothing is sent.
        (new CheckServerReachability($server->id))->handle($down);
        $this->assertSame(0, AppNotification::query()->count());

        // The second consecutive failure is.
        (new CheckServerReachability($server->id))->handle($down);
        $this->assertSame(1, AppNotification::query()->count());

        // And it does not repeat itself for every check that follows.
        (new CheckServerReachability($server->id))->handle($down);
        $this->assertSame(1, AppNotification::query()->count());

        // Recovery is worth exactly one message.
        $up = $this->fakeChecker([['kind' => 'tcp', 'port' => 2222, 'ok' => true, 'latency_ms' => 20, 'error' => null]]);
        (new CheckServerReachability($server->id))->handle($up);
        $this->assertSame(2, AppNotification::query()->count());
    }

    #[Test]
    public function a_recovery_inside_the_grace_period_stays_silent(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $down = $this->fakeChecker([['kind' => 'tcp', 'port' => 2222, 'ok' => false, 'latency_ms' => null, 'error' => 'timeout']]);
        (new CheckServerReachability($server->id))->handle($down);

        $up = $this->fakeChecker([['kind' => 'tcp', 'port' => 2222, 'ok' => true, 'latency_ms' => 20, 'error' => null]]);
        (new CheckServerReachability($server->id))->handle($up);

        // Nobody was told it was down, so nobody needs telling it is back.
        $this->assertSame(0, AppNotification::query()->count());
    }

    #[Test]
    public function the_checks_endpoint_summarises_uptime_and_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        foreach ([true, true, false, true] as $ok) {
            ServerCheck::query()->create([
                'server_id' => $server->id,
                'kind' => 'tcp',
                'port' => 2222,
                'ok' => $ok,
                'latency_ms' => $ok ? 20 : null,
                'error' => $ok ? null : 'timeout',
            ]);
        }

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/checks")->assertOk()->json();
        $this->assertSame(24, $body['hours']);
        $this->assertCount(1, $body['checks']);
        $this->assertSame(75.0, (float) $body['checks'][0]['uptime_pct']);
        $this->assertSame('SSH', $body['checks'][0]['label']);
        $this->assertSame(4, $body['checks'][0]['samples']);

        // Another owner cannot read it, and does not learn that it exists.
        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/checks")
            ->assertNotFound();
    }

    #[Test]
    public function monitor_ports_round_trip_and_reject_nonsense(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->actingAs($user)->putJson("/api/v1/servers/{$server->id}", [
            'monitor_ports' => [
                ['port' => 443, 'label' => 'https'],
                ['port' => 443, 'label' => 'duplicate'],
                ['port' => 8080, 'label' => ''],
            ],
        ])->assertOk();

        $server->refresh();
        $this->assertSame(
            [['port' => 443, 'label' => 'https'], ['port' => 8080, 'label' => null]],
            $server->monitorPorts(),
            'a duplicate port is dropped and a blank label becomes null'
        );

        $this->actingAs($user)->putJson("/api/v1/servers/{$server->id}", [
            'monitor_ports' => [['port' => 70000]],
        ])->assertStatus(422);
    }

    /**
     * @param  list<array{kind:string,port:int|null,ok:bool,latency_ms:int|null,error:string|null}>  $results
     */
    private function fakeChecker(array $results): ReachabilityChecker
    {
        return new class($results) extends ReachabilityChecker
        {
            /** @param list<array{kind:string,port:int|null,ok:bool,latency_ms:int|null,error:string|null}> $results */
            public function __construct(private array $results) {}

            public function check(string $host, array $ports): array
            {
                return $this->results;
            }
        };
    }

    /** @param list<array{port:int,label:string|null}> $ports */
    private function server(User $owner, array $ports = []): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => $owner->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 2222,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'KEY', 'passphrase' => ''],
            'host_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            // A stored pin means an update need not re-scan the host — which it
            // cannot do from a test.
            'host_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000',
            'enabled' => true,
            'monitor_ports' => $ports,
        ])->save();

        return $server;
    }
}
