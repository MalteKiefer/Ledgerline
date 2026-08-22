<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\BanManager;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerBanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function both_daemons_are_listed_side_by_side(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe(implode("\n", [
            '##LL:f2b',
            'JAIL sshd',
            '10.0.0.5 203.0.113.9',
            'JAIL nginx-badbots',
            '198.51.100.7',
            '##LL:csd',
            '[{"decisions":[{"value":"192.0.2.4","scenario":"crowdsecurity/ssh-bf","duration":"3h59m"}]}]',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/bans")->assertOk()->json();

        // An address banned by one daemon is unknown to the other, so neither
        // list may stand in for the other.
        $this->assertSame(['sshd', 'nginx-badbots'], array_column($body['fail2ban'], 'jail'));
        $this->assertSame(['10.0.0.5', '203.0.113.9'], $body['fail2ban'][0]['ips']);
        $this->assertSame('192.0.2.4', $body['crowdsec'][0]['ip']);
        $this->assertSame('crowdsecurity/ssh-bf', $body['crowdsec'][0]['reason']);
    }

    #[Test]
    public function an_unban_reaches_the_host_quoted_and_is_audited(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe("##LL:rc=0\n");

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/bans", [
                'daemon' => 'fail2ban', 'action' => 'unban', 'ip' => '10.0.0.5', 'jail' => 'sshd',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString("fail2ban-client set 'sshd' unbanip '10.0.0.5'", (string) $probe->script);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.ban_action')->count());
    }

    #[Test]
    public function fail2ban_allow_says_it_only_unbanned(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("##LL:rc=0\n");

        // Claiming a permanent allow-list entry that fail2ban does not have at
        // runtime would be a lie the next restart exposes.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/bans", [
                'daemon' => 'fail2ban', 'action' => 'allow', 'ip' => '10.0.0.5', 'jail' => 'sshd',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('error', 'f2b_allow_is_manual');
    }

    #[Test]
    public function something_that_is_not_an_address_never_reaches_the_host(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/bans", [
                'daemon' => 'crowdsec', 'action' => 'ban', 'ip' => '10.0.0.5; rm -rf /',
            ])
            ->assertStatus(422);

        $this->assertNull($probe->script);
    }

    #[Test]
    public function a_fail2ban_action_without_a_jail_is_refused(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->fakeProbe('should never run');

        // fail2ban acts per jail; sending the command without one would either
        // fail on the host or hit a jail nobody chose.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/bans", [
                'daemon' => 'fail2ban', 'action' => 'unban', 'ip' => '10.0.0.5',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'jail_required');

        $this->assertNull($probe->script);
    }

    #[Test]
    public function bans_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("##LL:end\n");

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/bans")
            ->assertNotFound();
    }

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
        $this->swap(BanManager::class, new BanManager($probe));

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
