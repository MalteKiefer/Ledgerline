<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Servers\SecurityAudit;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_layer_is_reported_not_just_the_first(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe(implode("\n", [
            '##LL:nft',
            'table inet filter {',
            '  chain input {',
            '  chain forward {',
            '##LL:iptables',
            '-P INPUT DROP',
            '-A INPUT -p tcp --dport 22 -j ACCEPT',
            '##LL:ip6tables',
            '__absent__',
            '##LL:ufw',
            'Status: active',
            '##LL:firewalld',
            '__absent__',
            '##LL:fail2ban',
            'Status',
            'Jail list:	sshd, nginx-badbots',
            '##LL:crowdsec',
            '__absent__',
            '##LL:selinux',
            '__absent__',
            '##LL:apparmor',
            'enabled',
            '##LL:sshd',
            'permitrootlogin no',
            'passwordauthentication no',
            '##LL:unattended',
            'enabled',
            '##LL:reboot',
            'no',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        // A host running nftables under iptables under ufw must show all three;
        // naming only the first hides the one actually deciding.
        $names = array_column($body['firewalls'], 'name');
        $this->assertSame(['nftables', 'iptables', 'ufw', 'apparmor'], $names);
        $this->assertSame('active', $body['firewalls'][2]['summary']);
        $this->assertSame('fail2ban', $body['bans'][0]['name']);
        $this->assertStringContainsString('2 jails', $body['bans'][0]['summary']);
        $this->assertSame('no', $body['ssh']['permitrootlogin']);
        $this->assertTrue($body['updates']['unattended']);
        $this->assertFalse($body['updates']['reboot_required']);
    }

    #[Test]
    public function a_firewall_we_cannot_read_is_not_reported_as_empty(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        // What an unprivileged monitoring account gets. "cannot read" and "no
        // rules" are opposite answers and must never collapse into one.
        $this->fakeProbe(implode("\n", [
            '##LL:nft',
            'Error: Could not process rule: Operation not permitted',
            '##LL:iptables',
            'iptables v1.8.9 (nf_tables): Permission denied (you must be root)',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        foreach ($body['firewalls'] as $f) {
            $this->assertFalse($f['readable'], $f['name'].' must be flagged unreadable');
            $this->assertNull($f['active']);
            $this->assertSame('', $f['detail'], 'a permission error is not rule content');
        }
    }

    #[Test]
    public function a_wildcard_bind_is_exposed_and_a_loopback_one_is_not(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        // What `ss -tulpnH` actually prints.
        $this->fakeProbe(implode("\n", [
            '##LL:listen',
            'tcp   LISTEN 0      128    0.0.0.0:22      0.0.0.0:*    users:(("sshd",pid=812,fd=3))',
            'tcp   LISTEN 0      511    127.0.0.1:5432  0.0.0.0:*    users:(("postgres",pid=901,fd=5))',
            'tcp   LISTEN 0      511    [::]:80         [::]:*       users:(("nginx",pid=1002,fd=6))',
            'udp   UNCONN 0      0      127.0.0.53:53   0.0.0.0:*    users:(("systemd-resolve",pid=700,fd=12))',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        $ports = array_column($body['listening'], 'port');
        $this->assertSame([22, 5432, 80, 53], $ports);

        $byPort = array_column($body['listening'], null, 'port');
        // The line worth reading: a wildcard bind is what the outside can
        // reach, and listing it the same way as a loopback service would hide
        // exactly the difference that decides reachability.
        $this->assertTrue($byPort[22]['exposed']);
        $this->assertSame('0.0.0.0', $byPort[22]['address']);
        $this->assertSame('sshd', $byPort[22]['process']);
        $this->assertFalse($byPort[5432]['exposed'], 'a service on 127.0.0.1 is not reachable from outside');
        // "::" is the v6 wildcard and means the same thing as 0.0.0.0.
        $this->assertTrue($byPort[80]['exposed']);
        $this->assertSame('::', $byPort[80]['address']);
        $this->assertFalse($byPort[53]['exposed'], 'the stub resolver on 127.0.0.53 is loopback');

        // The exposed list is the listening one, filtered — not a second,
        // separately-derived answer that could drift from it.
        $this->assertSame([22, 80], array_column($body['exposed'], 'port'));
    }

    #[Test]
    public function the_same_port_on_v4_and_v6_is_one_service_not_two(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe(implode("\n", [
            '##LL:listen',
            'tcp   LISTEN 0      128    0.0.0.0:22    0.0.0.0:*    users:(("sshd",pid=812,fd=3))',
            'tcp   LISTEN 0      128    [::]:22       [::]:*       users:(("sshd",pid=812,fd=4))',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        // One socket per line, but sshd bound to both stacks is one service,
        // and showing it twice reads as a misconfiguration that is not there.
        $this->assertCount(1, $body['listening']);
        $this->assertSame(22, $body['listening'][0]['port']);
    }

    #[Test]
    public function lines_that_are_not_sockets_are_skipped_rather_than_half_parsed(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe(implode("\n", [
            '##LL:listen',
            // The netstat fallback prints a header the caller already trims,
            // plus unix sockets that carry no port at all.
            'Proto Recv-Q Send-Q Local Address           Foreign Address         State',
            'unix  2      [ ACC ]     STREAM     LISTENING     12345    /run/docker.sock',
            'tcp   LISTEN 0      128    0.0.0.0:22    0.0.0.0:*    users:(("sshd",pid=812,fd=3))',
            '##LL:end',
            '',
        ]));

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        $this->assertSame([22], array_column($body['listening'], 'port'));
    }

    #[Test]
    public function a_host_that_reports_nothing_lists_nothing_rather_than_failing(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("##LL:end\n");

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/security")->assertOk()->json();

        $this->assertSame([], $body['listening']);
        $this->assertSame([], $body['exposed']);
        $this->assertSame([], $body['firewalls'], 'nothing found is not the same as a finding');
    }

    #[Test]
    public function the_audit_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $this->fakeProbe("##LL:end\n");

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/security")
            ->assertNotFound();
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
        $this->swap(SecurityAudit::class, new SecurityAudit($probe));

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
