<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Servers\LogReader;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_discovers_what_the_host_can_offer(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("##LL:journal\nyes\n##LL:units\nssh.service\nnginx.service\n##LL:containers\nweb\n##LL:files\n/var/log/syslog\n##LL:end\n");

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/log-sources")->assertOk()->json();

        $this->assertTrue($body['journal']);
        $this->assertSame(['ssh.service', 'nginx.service'], $body['units']);
        $this->assertSame(['web'], $body['containers']);
        $this->assertSame(['/var/log/syslog'], $body['files']);
    }

    #[Test]
    public function it_reads_a_journal_tail(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe("2026-08-21T10:00:00 srv sshd[1]: Accepted publickey\n");

        $body = $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'journal', 'unit' => 'ssh.service', 'lines' => 50])
            ->assertOk()
            ->json();

        $this->assertStringContainsString('Accepted publickey', $body['text']);
        // The unit reaches the host as an argument to a fixed command, quoted.
        $this->assertStringContainsString("journalctl --no-pager --output=short-iso -n 50 -u 'ssh.service'", $probe->script);
    }

    #[Test]
    public function it_refuses_a_unit_name_that_is_not_one(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        // There is no shell to escape here — the script is fed to `sh -s` on
        // stdin — but a name that cannot be a unit is refused before a
        // connection is opened at all.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'journal', 'unit' => 'ssh.service; rm -rf /'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_selection');

        $this->assertNull($probe->script, 'nothing may reach the host once the selection is rejected');
    }

    #[Test]
    public function it_refuses_a_file_outside_the_log_directory(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        foreach (['/etc/shadow', '/var/log/../../etc/passwd', '/root/.ssh/id_ed25519'] as $path) {
            $this->actingAs($user)
                ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'file', 'path' => $path])
                ->assertStatus(422)
                ->assertJsonPath('error', 'invalid_selection');
        }

        $this->assertNull($probe->script);
    }

    #[Test]
    public function it_caps_the_line_count_the_request_asks_for(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('ok');

        // The ceiling is enforced by validation, so an absurd count never
        // reaches the host at all.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'journal', 'lines' => 99999])
            ->assertStatus(422);
        $this->assertNull($probe->script);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'journal', 'lines' => 2000])
            ->assertOk();
        $this->assertStringContainsString('-n 2000', (string) $probe->script);
    }

    #[Test]
    public function logs_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe('secret log lines');

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/servers/{$server->id}/logs", ['source' => 'journal'])
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/log-sources")
            ->assertNotFound();
    }

    /** Swap the transport for one that records what it was asked to run. */
    private function fakeProbe(string $output): ServerProbe
    {
        $probe = new class($output) extends ServerProbe
        {
            public ?string $script = null;

            public function __construct(private string $output) {}

            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false): array
            {
                $this->script = $script;

                return ['ok' => true, 'out' => $this->output, 'err' => '', 'exit' => 0];
            }
        };

        $this->swap(ServerProbe::class, $probe);
        $this->swap(LogReader::class, new LogReader($probe));

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
