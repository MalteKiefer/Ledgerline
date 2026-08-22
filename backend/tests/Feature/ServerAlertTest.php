<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\ProbeResult;
use App\Services\Servers\ServerMonitor;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hardware alerts and the per-server thresholds that govern them.
 *
 * Two properties carry the whole feature. A defect has to be announced once, on
 * the transition — a drive that has been failing since Tuesday must not produce
 * a notification every quarter hour. And a state we could not read is not a
 * defect: a host without smartmontools reports "unknown", and treating that as
 * a failure teaches the owner to ignore the message that matters.
 */
class ServerAlertTest extends TestCase
{
    use RefreshDatabase;

    private const FP = 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    /** Feeds the monitor canned results instead of opening an SSH session. */
    private function monitorReturning(ProbeResult ...$results): ServerMonitor
    {
        $probe = new class($results) extends ServerProbe
        {
            /** @param  list<ProbeResult>  $queue */
            public function __construct(private array $queue) {}

            public function run(ServerTarget $target, bool $interactive = false): ProbeResult
            {
                return array_shift($this->queue) ?? new ProbeResult(false, error: 'exhausted');
            }
        };

        return new ServerMonitor($probe);
    }

    /** @param  array<string, mixed>  $columns */
    private function server(array $columns = []): Server
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
            'host_fingerprint' => self::FP,
            'enabled' => true,
            ...$columns,
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

    private function serverNotifications(): int
    {
        return AppNotification::query()->where('category', 'server')->count();
    }

    public function test_a_failing_drive_is_announced_once_and_not_again(): void
    {
        $server = $this->server();
        $healthy = ['storage' => [['name' => 'sda', 'health' => 'ok']]];
        $failing = ['storage' => [['name' => 'sda', 'health' => 'failing']]];
        $monitor = $this->monitorReturning(
            $this->okResult($healthy),
            $this->okResult($failing),
            $this->okResult($failing),
        );

        $monitor->refresh($server);
        $this->assertSame(0, $this->serverNotifications());

        $monitor->refresh($server);
        $this->assertSame(1, $this->serverNotifications());

        // Still failing on the next poll: recording continues, notifying stops.
        $monitor->refresh($server);
        $this->assertSame(1, $this->serverNotifications());
    }

    public function test_reallocated_sectors_count_as_a_defect_even_when_the_verdict_says_ok(): void
    {
        // A drive that has started remapping is on its way out whatever its
        // overall self-assessment claims, and nobody finds this by looking.
        $server = $this->server();
        $monitor = $this->monitorReturning(
            $this->okResult(['storage' => [['name' => 'sdb', 'health' => 'ok', 'reallocated' => 0]]]),
            $this->okResult(['storage' => [['name' => 'sdb', 'health' => 'ok', 'reallocated' => 4]]]),
            $this->okResult(['storage' => [['name' => 'sdb', 'health' => 'ok', 'reallocated' => 4]]]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);
        $monitor->refresh($server);

        $rows = AppNotification::query()->where('category', 'server')->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('sdb', (string) $rows[0]->title);
    }

    public function test_a_drive_whose_health_cannot_be_read_is_not_a_defect(): void
    {
        // The one that decides whether the alert is trusted: a missing tool is
        // not a broken disk.
        $server = $this->server();
        $unknown = ['storage' => [['name' => 'sda', 'health' => 'unknown', 'reallocated' => null, 'pending' => null]]];
        $monitor = $this->monitorReturning($this->okResult($unknown), $this->okResult($unknown));

        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(0, $this->serverNotifications());
    }

    public function test_a_degraded_array_is_announced_once_and_a_healthy_one_never(): void
    {
        // An array keeps working right up until the second disk goes, so the
        // first failure is the only chance to say it.
        $server = $this->server();
        $healthy = ['arrays' => [['name' => 'md0', 'degraded' => false]]];
        $degraded = ['arrays' => [['name' => 'md0', 'degraded' => true]]];
        $monitor = $this->monitorReturning(
            $this->okResult($healthy),
            $this->okResult($degraded),
            $this->okResult($degraded),
        );

        $monitor->refresh($server);
        $this->assertSame(0, $this->serverNotifications());

        $monitor->refresh($server);
        $monitor->refresh($server);

        $rows = AppNotification::query()->where('category', 'server')->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('md0', (string) $rows[0]->title);
    }

    public function test_a_per_server_disk_threshold_replaces_the_default(): void
    {
        // 90% is right for a system disk and nonsense for an archive that lives
        // at 95 and always has.
        $server = $this->server(['disk_alert_pct' => 95]);
        $monitor = $this->monitorReturning(
            $this->okResult(['disks' => [['mount' => '/data', 'used_pct' => 92.0]]]),
            $this->okResult(['disks' => [['mount' => '/data', 'used_pct' => 96.0]]]),
        );

        // Past the built-in 90 but below this server's own limit: quiet.
        $monitor->refresh($server);
        $this->assertSame(0, $this->serverNotifications());

        $monitor->refresh($server);
        $this->assertSame(1, $this->serverNotifications());
    }

    public function test_memory_alerts_on_the_crossing_of_the_configured_limit(): void
    {
        $server = $this->server(['mem_alert_pct' => 80]);
        $monitor = $this->monitorReturning(
            $this->okResult(['mem' => ['used_pct' => 60.0]]),
            $this->okResult(['mem' => ['used_pct' => 85.0]]),
            $this->okResult(['mem' => ['used_pct' => 88.0]]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(1, $this->serverNotifications());
    }

    public function test_temperature_uses_the_hottest_sensor_and_the_default_when_unset(): void
    {
        // An average would hide the one sensor that is actually running hot.
        $server = $this->server();
        $monitor = $this->monitorReturning(
            $this->okResult(['sensors' => [['label' => 'pkg', 'temp_c' => 45.0]]]),
            $this->okResult(['sensors' => [['label' => 'pkg', 'temp_c' => 50.0], ['label' => 'nvme', 'temp_c' => 91.0]]]),
            $this->okResult(['sensors' => [['label' => 'pkg', 'temp_c' => 50.0], ['label' => 'nvme', 'temp_c' => 92.0]]]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(1, $this->serverNotifications());
    }

    public function test_a_server_without_sensors_or_drives_stays_quiet(): void
    {
        $server = $this->server();
        $monitor = $this->monitorReturning($this->okResult(), $this->okResult());

        $monitor->refresh($server);
        $monitor->refresh($server);

        $this->assertSame(0, $this->serverNotifications());
    }

    public function test_alert_notifications_carry_real_text_not_a_translation_key(): void
    {
        // A missing translation surfaces as the raw dotted path in the user's
        // notification centre, which reads as a bug and hides the message.
        $server = $this->server();
        $monitor = $this->monitorReturning(
            $this->okResult(),
            $this->okResult([
                'storage' => [['name' => 'sda', 'health' => 'failing']],
                'arrays' => [['name' => 'md0', 'degraded' => true]],
                'mem' => ['used_pct' => 99.0],
                'sensors' => [['label' => 'nvme', 'temp_c' => 95.0]],
            ]),
        );

        $monitor->refresh($server);
        $monitor->refresh($server);

        $titles = AppNotification::query()->where('category', 'server')->pluck('title')->all();
        $this->assertCount(4, $titles);
        foreach ($titles as $title) {
            $this->assertStringNotContainsString('servers.notify.', (string) $title);
            $this->assertStringContainsString('web01', (string) $title);
        }
    }
}
