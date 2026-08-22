<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\ServerControl;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerControlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_services_including_stopped_ones(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe(
            "ssh.service loaded active running OpenBSD Secure Shell server\n".
            "nginx.service loaded inactive dead A high performance web server\n".
            "not-a-unit.mount loaded active mounted Ignore me\n"
        );

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/services")->assertOk()->json();

        $this->assertCount(2, $body['units'], 'only .service units belong in a service list');
        $this->assertSame('nginx.service', $body['units'][1]['name']);
        $this->assertSame('inactive', $body['units'][1]['active']);
        // A stopped service must be listed, or it can never be started.
        $this->assertStringContainsString('--all', (string) $probe->script);
    }

    #[Test]
    public function a_service_action_reaches_the_host_quoted_and_is_audited(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe("##LL:rc=0\n");

        $body = $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/services", ['unit' => 'nginx.service', 'action' => 'restart'])
            ->assertOk()
            ->json();

        $this->assertTrue($body['ok']);
        $this->assertStringContainsString("systemctl restart 'nginx.service'", (string) $probe->script);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.service_action')->count());
    }

    #[Test]
    public function a_refusal_from_the_host_is_passed_through_rather_than_hidden(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        // What an unprivileged monitoring account actually gets back. Showing it
        // is the point: a button that quietly does nothing is worse.
        $this->fakeProbe("Failed to restart nginx.service: Interactive authentication required.\n##LL:rc=1\n");

        $body = $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/services", ['unit' => 'nginx.service', 'action' => 'restart'])
            ->assertOk()
            ->json();

        $this->assertFalse($body['ok']);
        $this->assertStringContainsString('Interactive authentication required', $body['output']);
        $this->assertStringNotContainsString('##LL:rc', $body['output']);
    }

    #[Test]
    public function an_action_that_is_not_offered_never_reaches_the_host(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/services", ['unit' => 'nginx.service', 'action' => 'mask'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/services", ['unit' => 'nginx.service; rm -rf /', 'action' => 'stop'])
            ->assertStatus(422);

        $this->assertNull($probe->script);
    }

    #[Test]
    public function pid_one_is_refused(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        // Signalling init takes the machine down; no interface should put that
        // one mis-click away.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/processes/signal", ['pid' => 1, 'signal' => 'KILL'])
            ->assertStatus(422);

        $this->assertNull($probe->script);
    }

    #[Test]
    public function a_signal_reaches_the_host_and_is_audited(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe("##LL:rc=0\n");

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/processes/signal", ['pid' => 4242, 'signal' => 'TERM'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('kill -TERM 4242', (string) $probe->script);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.process_signal')->count());
    }

    #[Test]
    public function processes_are_parsed_and_owner_scoped(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("  1234 root 2.5 10.1 512000 postgres\n  99 www-data 0.0 0.5 20480 nginx\n");

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/processes")->assertOk()->json();
        $this->assertSame(1234, $body['processes'][0]['pid']);
        $this->assertSame('postgres', $body['processes'][0]['command']);
        $this->assertSame(512000, $body['processes'][0]['rss_kb']);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/processes")
            ->assertNotFound();
    }

    #[Test]
    public function a_reboot_is_audited_before_it_is_sent(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('##LL:rc=0
');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/power", ['action' => 'reboot'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('systemctl reboot', (string) $probe->script);
        // Audited even if the machine never answers again: a host that vanishes
        // should still leave a record of who asked and for what.
        $this->assertSame(1, AuditLog::query()->where('action', 'server.power')->count());
    }

    #[Test]
    public function a_dropped_connection_during_a_reboot_is_success_not_failure(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        // The link going away is exactly what a reboot does; calling it an
        // error would teach the operator to ignore real errors.
        $this->deadProbe();

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/power", ['action' => 'reboot'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Cancelling a shutdown has no such excuse — silence there is a failure.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/power", ['action' => 'cancel'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'unreachable');
    }

    #[Test]
    public function a_power_verb_that_is_not_offered_never_reaches_the_host(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/power", ['action' => 'halt; rm -rf /'])
            ->assertStatus(422);

        $this->assertNull($probe->script);
    }

    #[Test]
    public function ending_a_session_signals_the_whole_terminal(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('##LL:rc=0
');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/sessions/kill", ['tty' => 'pts/3'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Everything attached to the terminal, not just the shell: killing the
        // shell alone leaves whatever it started running.
        $this->assertStringContainsString("pkill -KILL -t 'pts/3'", (string) $probe->script);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.session_killed')->count());
    }

    #[Test]
    public function a_terminal_name_that_is_not_a_terminal_is_refused(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/sessions/kill", ['tty' => 'pts/3; reboot'])
            ->assertStatus(422);

        $this->assertNull($probe->script);
    }

    /** A probe whose host never answers — what a reboot looks like from here. */
    private function deadProbe(): void
    {
        $probe = new class extends ServerProbe
        {
            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
            {
                return ['ok' => false, 'out' => '', 'err' => 'closed', 'exit' => 255];
            }
        };

        $this->swap(ServerProbe::class, $probe);
        $this->swap(ServerControl::class, new ServerControl($probe));
    }

    private function fakeProbe(string $output): ServerProbe
    {
        $probe = new class($output) extends ServerProbe
        {
            public ?string $script = null;

            public function __construct(private string $output) {}

            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
            {
                $this->script = $script;

                return ['ok' => true, 'out' => $this->output, 'err' => '', 'exit' => 0];
            }
        };

        $this->swap(ServerProbe::class, $probe);
        $this->swap(ServerControl::class, new ServerControl($probe));

        return $probe;
    }

    private function server(User $owner): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => $owner->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'KEY', 'passphrase' => ''],
            'host_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            'host_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000',
            'enabled' => true,
        ])->save();

        return $server;
    }
}
