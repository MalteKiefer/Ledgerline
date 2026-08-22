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

            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false): array
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
