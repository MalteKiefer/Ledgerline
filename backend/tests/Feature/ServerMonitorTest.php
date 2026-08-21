<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\ProbeResult;
use App\Services\Servers\ServerMonitor;
use App\Services\Servers\ServerProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The monitor's job is to record a run and notify on a CHANGE of state. Getting
 * that wrong is what turns monitoring into noise: a host that has been down for
 * two days must not produce a notification every quarter hour, and a filesystem
 * that is already over the threshold must not re-alert on every poll.
 */
class ServerMonitorTest extends TestCase
{
    use RefreshDatabase;

    /** Feeds the monitor canned results instead of opening an SSH session. */
    private function monitorReturning(ProbeResult ...$results): ServerMonitor
    {
        $probe = new class($results) extends ServerProbe
        {
            /** @param  list<ProbeResult>  $queue */
            public function __construct(private array $queue) {}

            /**
             * @param  array<string, mixed>  $credentials
             */
            public function run(
                string $host,
                int $port,
                string $username,
                string $authType,
                array $credentials,
                ?string $expectedFingerprint = null,
                bool $interactive = false,
            ): ProbeResult {
                return array_shift($this->queue) ?? new ProbeResult(false, error: 'exhausted');
            }
        };

        return new ServerMonitor($probe);
    }

    private function server(): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => User::factory()->create()->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'k'],
            'host_fingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'enabled' => true,
        ])->save();

        return $server;
    }

    /** @param  array<string, mixed>  $extra */
    private function okResult(array $extra = []): ProbeResult
    {
        return new ProbeResult(true, facts: [
            'reboot_required' => false,
            'failed_units' => [],
            'disks' => [['mount' => '/', 'used_pct' => 40.0]],
            ...$extra,
        ]);
    }

    public function test_a_first_failed_run_notifies_and_a_repeat_failure_stays_quiet(): void
    {
        $server = $this->server();
        $monitor = $this->monitorReturning(
            new ProbeResult(false, error: 'auth_failed'),
            new ProbeResult(false, error: 'auth_failed'),
        );

        $monitor->refresh($server);
        $this->assertSame(1, AppNotification::query()->where('category', 'server')->count());

        // Still down: recording continues, notifying does not.
        $monitor->refresh($server);
        $this->assertSame(1, AppNotification::query()->where('category', 'server')->count());
        $this->assertDatabaseCount('server_facts', 2);
    }

    public function test_recovery_notifies_too(): void
    {
        $server = $this->server();
        $monitor = $this->monitorReturning(new ProbeResult(false, error: 'auth_failed'), $this->okResult());

        $monitor->refresh($server);
        $monitor->refresh($server);

        // A resolved outage must be announced, otherwise the alarm stays standing.
        $titles = AppNotification::query()->where('category', 'server')->pluck('title')->all();
        $this->assertCount(2, $titles);
        $this->assertStringContainsString('web01', (string) $titles[1]);
    }

    public function test_a_healthy_first_run_says_nothing(): void
    {
        $monitor = $this->monitorReturning($this->okResult());

        $monitor->refresh($this->server());

        $this->assertSame(0, AppNotification::query()->where('category', 'server')->count());
    }

    public function test_disk_pressure_alerts_on_the_crossing_only(): void
    {
        $server = $this->server();
        $full = ['disks' => [['mount' => '/', 'used_pct' => 94.0]]];
        $monitor = $this->monitorReturning(
            $this->okResult(),               // 40% — quiet
            $this->okResult($full),          // crossed 90% — alert
            $this->okResult($full),          // still full — quiet
        );

        $monitor->refresh($server);
        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(1, AppNotification::query()->where('category', 'server')->count());
    }

    public function test_reboot_required_alerts_once(): void
    {
        $server = $this->server();
        $monitor = $this->monitorReturning(
            $this->okResult(),
            $this->okResult(['reboot_required' => true]),
            $this->okResult(['reboot_required' => true]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(1, AppNotification::query()->where('category', 'server')->count());
    }

    public function test_only_newly_failed_units_are_reported(): void
    {
        $server = $this->server();
        $monitor = $this->monitorReturning(
            $this->okResult(['failed_units' => ['nginx.service']]),
            // nginx still failing, postfix newly failing → one notification about
            // postfix, not a repeat about nginx.
            $this->okResult(['failed_units' => ['nginx.service', 'postfix.service']]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);

        $rows = AppNotification::query()->where('category', 'server')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('postfix.service', $rows[1]->body);
    }

    public function test_a_failed_run_records_the_reason_and_no_facts(): void
    {
        $fact = $this->monitorReturning(new ProbeResult(false, error: 'fingerprint_mismatch'))
            ->refresh($this->server());

        $this->assertFalse($fact->ok);
        $this->assertSame('fingerprint_mismatch', $fact->error);
        $this->assertNull($fact->facts);
    }
}
