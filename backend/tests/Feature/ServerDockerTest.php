<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\DockerInspector;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A probe that answers with canned output and keeps every script it was given. */
class DockerRecordingProbe extends ServerProbe
{
    /** @var list<string> */
    public array $scripts = [];

    public function __construct(private string $output = '##LL:rc=0') {}

    public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
    {
        $this->scripts[] = $script;

        return ['ok' => true, 'out' => $this->output, 'err' => '', 'exit' => 0];
    }

    public function sent(): string
    {
        return implode("\n", $this->scripts);
    }
}

class ServerDockerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_host_without_docker_is_not_reported_as_an_error(): void
    {
        $inspector = $this->inspector("##LL:version\n__absent__\n##LL:end\n");

        $result = $inspector->inspect($this->server(User::factory()->create()));

        // Nothing is wrong with this machine; it simply does not run Docker.
        $this->assertTrue($result['ok']);
        $this->assertFalse($result['present']);
        $this->assertNull($result['error']);
        $this->assertSame([], $result['containers']);
    }

    #[Test]
    public function a_permission_error_is_never_reported_as_an_absent_engine(): void
    {
        // What an account outside the docker group actually gets back.
        $inspector = $this->inspector(
            "##LL:version\n"
            ."permission denied while trying to connect to the Docker daemon socket at unix:///var/run/docker.sock\n"
            ."##LL:end\n"
        );

        $result = $inspector->inspect($this->server(User::factory()->create()));

        // The distinction that matters: reporting this as "not installed"
        // sends somebody looking for a package that is already there.
        $this->assertTrue($result['present'], 'the engine is installed, we just cannot talk to it');
        $this->assertFalse($result['ok']);
        $this->assertSame('no_access', $result['error']);
    }

    #[Test]
    public function a_refused_connection_is_also_an_access_problem_not_an_absence(): void
    {
        $inspector = $this->inspector(
            "##LL:version\n"
            ."Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?\n"
            ."##LL:end\n"
        );

        $result = $inspector->inspect($this->server(User::factory()->create()));

        $this->assertTrue($result['present']);
        $this->assertSame('no_access', $result['error']);
    }

    #[Test]
    public function an_unreachable_host_is_distinct_from_both(): void
    {
        $probe = new class extends ServerProbe
        {
            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
            {
                return ['ok' => false, 'out' => '', 'err' => 'timeout', 'exit' => 255];
            }
        };

        $result = (new DockerInspector($probe))->inspect($this->server(User::factory()->create()));

        $this->assertSame('unreachable', $result['error']);
        $this->assertFalse($result['present']);
    }

    #[Test]
    public function containers_carry_their_live_sample_and_an_absent_healthcheck_is_not_a_warning(): void
    {
        $inspector = $this->inspector(implode("\n", [
            '##LL:version',
            '27.1.1',
            '##LL:ps',
            "abc123def4567\tweb\tnginx:1.27\trunning\tUp 3 hours\t0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp\t3 hours ago",
            "def456abc7890\tdb\tpostgres:18\texited\tExited (0) 2 days ago\t\t2 days ago",
            '##LL:stats',
            "web\t1.42%\t120MiB / 2GiB\t5.86%\t1.2kB / 900B\t0B / 0B",
            '##LL:volumes',
            "pgdata\tlocal",
            '##LL:networks',
            "bridge\tbridge\tlocal",
            '##LL:compose',
            '[{"Name":"ledgerline","Status":"running(3)"}]',
            '##LL:health',
            "/web\thealthy\t0",
            "/db\t-\t4",
            '##LL:end',
            '',
        ]));

        $result = $inspector->inspect($this->server(User::factory()->create()));

        $this->assertTrue($result['ok']);
        $this->assertSame('27.1.1', $result['version']);
        $this->assertSame(['web', 'db'], array_column($result['containers'], 'name'));
        // Truncated the way Docker itself prints a short id.
        $this->assertSame('abc123def456', $result['containers'][0]['id']);
        $this->assertSame(['0.0.0.0:80->80/tcp', '0.0.0.0:443->443/tcp'], $result['containers'][0]['ports']);
        $this->assertSame('1.42%', $result['containers'][0]['cpu']);
        $this->assertSame('healthy', $result['containers'][0]['health']);
        // "-" means the image declares no healthcheck, which is not the same
        // as unhealthy and must never be shown as one.
        $this->assertSame('', $result['containers'][1]['health']);
        $this->assertSame(4, $result['containers'][1]['restarts']);
        // A stopped container has no live sample, and inventing zeroes would
        // read as "idle" rather than "not running".
        $this->assertNull($result['containers'][1]['cpu']);
        $this->assertSame(['ledgerline'], $result['compose']);

        // Images and disk usage are not in this sweep: both take tens of
        // seconds on a real engine and used to leave the whole tab empty.
        $this->assertSame([], $result['images']);
        $this->assertSame([], $result['disk']);
    }

    #[Test]
    public function images_and_disk_usage_come_from_their_own_call(): void
    {
        $inspector = $this->inspector(implode("\n", [
            '##LL:images',
            "nginx:1.27\tsha256abcdef01\t142MB\t3 weeks ago",
            '##LL:df',
            'Images          31        12        14.2GB    8.1GB (57%)',
            '##LL:end',
            '',
        ]));

        $result = $inspector->storage($this->server(User::factory()->create()));

        $this->assertTrue($result['ok']);
        // The client renders repository and tag apart; emitting one joined
        // "name" left it printing undefined next to every image.
        $this->assertSame('nginx', $result['images'][0]['repo']);
        $this->assertSame('1.27', $result['images'][0]['tag']);
        $this->assertSame(['type' => 'Images', 'total' => 31, 'active' => 12, 'size' => '14.2GB', 'reclaimable' => '8.1GB (57%)'], $result['disk'][0]);
    }

    #[Test]
    public function a_call_that_ran_out_of_time_is_not_an_unreachable_host(): void
    {
        $probe = new class extends ServerProbe
        {
            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
            {
                // What Symfony says when our own budget runs out, as opposed to
                // ssh reporting it never got an answer.
                return ['ok' => false, 'out' => '', 'err' => 'The process "ssh" exceeded the timeout of 45 seconds.', 'exit' => null];
            }
        };

        $result = (new DockerInspector($probe))->storage($this->server(User::factory()->create()));

        // The engine keeps counting after we stop waiting, so this is worth
        // telling apart from a host that cannot be reached at all.
        $this->assertSame('timeout', $result['error']);
    }

    #[Test]
    public function a_verb_outside_the_fixed_set_never_reaches_the_host(): void
    {
        $probe = new DockerRecordingProbe;
        $inspector = new DockerInspector($probe);
        $server = $this->server(User::factory()->create());

        // The terminal exists for anything beyond the fixed set, and it asks
        // for the account password first.
        foreach (['exec', 'run', 'build', 'cp', 'rm -rf', ''] as $verb) {
            $this->assertSame('invalid_selection', $inspector->act($server, 'web', $verb)['error'], $verb);
        }

        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function a_name_that_is_not_a_container_name_never_reaches_the_host(): void
    {
        $probe = new DockerRecordingProbe;
        $inspector = new DockerInspector($probe);
        $server = $this->server(User::factory()->create());

        foreach ([
            'web; rm -rf /',
            'web && reboot',
            '$(whoami)',
            '-web',
            'web name',
            '',
            str_repeat('a', 129),
        ] as $name) {
            $this->assertSame('invalid_selection', $inspector->act($server, $name, 'stop')['error'], $name);
        }

        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function removing_is_rm_and_does_not_stop_the_container_first(): void
    {
        $probe = new DockerRecordingProbe;

        $result = (new DockerInspector($probe))->act($this->server(User::factory()->create()), 'web', 'remove');

        $this->assertTrue($result['ok']);
        // Stopping is a separate decision and should be a separate click; rm
        // refuses a running container rather than taking it down silently.
        $this->assertStringContainsString("docker rm 'web'", $probe->sent());
        $this->assertStringNotContainsString('docker stop', $probe->sent());
    }

    #[Test]
    public function a_docker_failure_is_reported_without_the_marker_leaking(): void
    {
        $probe = new DockerRecordingProbe("Error response from daemon: No such container: web\n##LL:rc=1");

        $result = (new DockerInspector($probe))->act($this->server(User::factory()->create()), 'web', 'stop');

        $this->assertFalse($result['ok']);
        $this->assertSame('command_failed', $result['error']);
        $this->assertSame('Error response from daemon: No such container: web', $result['output']);
    }

    #[Test]
    public function a_prune_target_outside_the_fixed_set_never_reaches_the_host(): void
    {
        $probe = new DockerRecordingProbe;
        $inspector = new DockerInspector($probe);
        $server = $this->server(User::factory()->create());

        foreach (['system', 'all', 'images -a', '', 'volumes; reboot'] as $target) {
            $this->assertSame('invalid_selection', $inspector->prune($server, $target)['error'], $target);
        }

        // `docker system prune -a --volumes` is the command that eats a day's
        // work, and it must not be reachable from here at all.
        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function a_prune_never_asks_for_more_than_the_named_target(): void
    {
        $probe = new DockerRecordingProbe;

        $result = (new DockerInspector($probe))->prune($this->server(User::factory()->create()), 'images');

        $this->assertTrue($result['ok']);
        $sent = $probe->sent();
        $this->assertStringContainsString('docker images prune -f', $sent);
        // Never -a: "unused since nothing references it" and "unused since
        // nothing is running" differ by a day's worth of images.
        $this->assertStringNotContainsString(' -a', $sent);
        $this->assertStringNotContainsString('--volumes', $sent);
    }

    /**
     * The payload has to answer "is there an engine here", not leave a caller
     * to recombine two flags. This shipped wrong once: the tab drew "no
     * container engine" over a host running seventeen containers, because
     * `available` was only ever set on the failure paths.
     */
    #[Test]
    public function the_payload_says_plainly_whether_an_engine_is_available(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->inspector(implode("\n", [
            '##LL:version',
            '27.1.1',
            '##LL:ps',
            "abc123def4567\tweb\tnginx:1.27\trunning\tUp 3 hours\t\t3 hours ago\tledgerline",
            '##LL:end',
            '',
        ]));

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/docker")
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('error', null)
            // The compose project a container belongs to is what makes a busy
            // host readable, so it travels with the container.
            ->assertJsonPath('containers.0.compose', 'ledgerline');

    }

    #[Test]
    public function an_engine_that_is_not_installed_is_not_available(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->inspector("##LL:version\n__absent__\n##LL:end\n");

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/docker")
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('error', null);
    }

    #[Test]
    public function an_engine_this_account_may_not_reach_is_not_available_either(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->inspector("##LL:version\npermission denied while trying to connect\n##LL:end\n");

        // Present, but out of reach - the opposite answer to "not installed",
        // and the reason the two never share a message.
        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/docker")
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('error', 'no_access');
    }

    #[Test]
    public function the_docker_endpoints_are_owner_scoped(): void
    {
        $server = $this->server(User::factory()->create());
        $this->inspector("##LL:version\n27.1.1\n##LL:end\n");

        $stranger = User::factory()->create();

        // 404, never 403: a stranger learns nothing about which servers exist.
        $this->actingAs($stranger)->getJson("/api/v1/servers/{$server->id}/docker")->assertNotFound();
        $this->actingAs($stranger)
            ->postJson("/api/v1/servers/{$server->id}/docker/action", ['name' => 'web', 'action' => 'stop'])
            ->assertNotFound();
        $this->actingAs($stranger)
            ->postJson("/api/v1/servers/{$server->id}/docker/prune", ['target' => 'images'])
            ->assertNotFound();
    }

    #[Test]
    public function the_endpoint_refuses_a_verb_it_does_not_know(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = new DockerRecordingProbe;
        $this->swap(DockerInspector::class, new DockerInspector($probe));

        // Refused by validation, so 422 rather than a redirect.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/docker/action", ['name' => 'web', 'action' => 'exec'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/docker/prune", ['target' => 'system'])
            ->assertStatus(422);

        $this->assertSame([], $probe->scripts);
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'server.docker%')->count());
    }

    #[Test]
    public function every_change_to_the_engine_is_audited_with_its_target_named(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->swap(DockerInspector::class, new DockerInspector(new DockerRecordingProbe));

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/docker/action", ['name' => 'web', 'action' => 'restart'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/docker/prune", ['target' => 'volumes'])
            ->assertOk();

        // A container nobody remembers removing is worse than one still there.
        $act = AuditLog::query()->where('action', 'server.docker_action')->firstOrFail();
        $this->assertSame('web', $act->meta['container']);
        $this->assertSame('restart', $act->meta['action']);

        $prune = AuditLog::query()->where('action', 'server.docker_prune')->firstOrFail();
        $this->assertSame('volumes', $prune->meta['target']);
    }

    #[Test]
    public function reading_the_engine_is_never_cached(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->inspector("##LL:version\n27.1.1\n##LL:end\n");

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/docker")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('present', true);
    }

    private function inspector(string $output): DockerInspector
    {
        $inspector = new DockerInspector(new DockerRecordingProbe($output));
        $this->swap(DockerInspector::class, $inspector);

        return $inspector;
    }

    private function server(User $owner): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => $owner->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'KEY', 'passphrase' => ''],
            'host_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            'host_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000',
            'enabled' => true,
        ])->save();

        return $server;
    }
}
