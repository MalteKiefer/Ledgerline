<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use App\Services\Servers\VpnInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A probe that answers from a queue and keeps the script it was handed. */
class VpnRecordingProbe extends ServerProbe
{
    /** @var list<string> */
    public array $scripts = [];

    /** @param  list<array{ok:bool,out:string}>  $queue */
    public function __construct(private array $queue = []) {}

    public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false, ?int $timeout = null): array
    {
        $this->scripts[] = $script;
        $next = array_shift($this->queue) ?? ['ok' => true, 'out' => ''];

        return ['ok' => $next['ok'], 'out' => $next['out'], 'err' => '', 'exit' => $next['ok'] ? 0 : 255];
    }

    public function sent(): string
    {
        return implode("\n", $this->scripts);
    }
}

/**
 * Overlay networks on a monitored host.
 *
 * Two properties carry the weight. Every provider is read from its machine
 * format rather than the text meant for a person -- a peer name with a space in
 * it is not a parsing problem when the format says where the field ends. And
 * taking the network down usually drops the connection the command arrived
 * over, which is the effect, not a failure.
 */
class ServerVpnTest extends TestCase
{
    use RefreshDatabase;

    private function server(User $owner): Server
    {
        return Server::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Edge',
            'host' => 'edge.example',
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'x'],
            'host_key' => 'ssh-ed25519 AAAA test',
            'host_fingerprint' => 'SHA256:test',
            'enabled' => true,
        ]);
    }

    #[Test]
    public function it_reads_netbird_from_its_json(): void
    {
        $json = json_encode([
            'netbirdIp' => '100.78.193.129/16',
            'fqdn' => 'srv.netbird.cloud',
            'daemonVersion' => '0.76.1',
            'management' => ['connected' => true],
            'signal' => ['connected' => true],
            'relays' => [['available' => true], ['available' => false]],
            'peers' => ['total' => 2, 'connected' => 1, 'details' => [
                ['fqdn' => 'proxy.netbird.cloud', 'netbirdIp' => '100.78.22.124', 'status' => 'Connected', 'connectionType' => 'Relayed', 'transferReceived' => 486252, 'transferSent' => 3599492],
                ['fqdn' => 'fedora.netbird.cloud', 'netbirdIp' => '100.78.103.138', 'status' => 'Idle', 'connectionType' => '-'],
            ]],
        ], JSON_THROW_ON_ERROR);

        $out = "##LL:netbird\n{$json}\n##LL:netbird_unit\nLoadState=loaded\nActiveState=active\nSubState=running\n##LL:end\n";
        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        $this->assertCount(1, $result['providers']);
        $netbird = $result['providers'][0];
        $this->assertSame('netbird', $netbird['id']);
        $this->assertTrue($netbird['connected']);
        $this->assertSame('100.78.193.129/16', $netbird['address']);
        $this->assertSame('1/2', $netbird['facts']['relays']);
        $this->assertSame(1, $netbird['peers_connected']);
        $this->assertSame('Relayed', $netbird['peers'][0]['route']);
        $this->assertSame(486252, $netbird['peers'][0]['rx']);
    }

    #[Test]
    public function a_host_without_any_vpn_reports_none_rather_than_an_empty_provider(): void
    {
        $out = "##LL:netbird\n__absent__\n##LL:netbird_unit\nLoadState=not-found\n"
            ."##LL:tailscale\n__absent__\n##LL:tailscale_unit\nLoadState=not-found\n"
            ."##LL:zt_info\n__absent__\n##LL:zt_unit\nLoadState=not-found\n"
            ."##LL:wg\n__absent__\n##LL:wg_units\n\n##LL:ovpn_units\n\n##LL:ovpn_conf\n\n##LL:links\nlo\neth0\n##LL:end\n";

        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['providers']);
    }

    #[Test]
    public function wireguard_calls_a_peer_connected_only_on_a_recent_handshake(): void
    {
        $fresh = time() - 30;
        $stale = time() - 7200;
        $dump = implode("\n", [
            "wg0\tprivkey\tpubkey\t51820\toff",
            "wg0\tpeerA\t(none)\t198.51.100.4:51820\t10.0.0.2/32\t{$fresh}\t1024\t2048\t25",
            "wg0\tpeerB\t(none)\t198.51.100.9:51820\t10.0.0.3/32\t{$stale}\t0\t0\toff",
        ]);

        $out = "##LL:netbird\n__absent__\n##LL:netbird_unit\nLoadState=not-found\n##LL:wg\n{$dump}\n##LL:links\nwg0\n##LL:end\n";
        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        $wg = $result['providers'][0];
        $this->assertSame('wireguard', $wg['id']);
        $this->assertSame('wg0', $wg['interfaces'][0]['name']);
        $this->assertSame(51820, $wg['interfaces'][0]['port']);
        // A handshake two hours old is not a live peer, whatever the config says.
        $this->assertSame('Connected', $wg['peers'][0]['status']);
        $this->assertSame('Idle', $wg['peers'][1]['status']);
        $this->assertSame(1, $wg['peers_connected']);
    }

    #[Test]
    public function wireguard_says_it_could_not_look_rather_than_reporting_no_tunnels(): void
    {
        $out = "##LL:netbird\n__absent__\n##LL:netbird_unit\nLoadState=not-found\n##LL:wg\n__noaccess__\n##LL:links\n##LL:end\n";
        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        // Reading the dump needs privilege. "Denied" and "no tunnels" are
        // opposite answers and must not look the same.
        $this->assertSame('denied', $result['providers'][0]['facts']['access']);
        $this->assertTrue($result['providers'][0]['installed']);
    }

    #[Test]
    public function a_bare_openvpn_package_is_not_a_vpn(): void
    {
        // Debian's plain openvpn.service starts nothing -- it collects the
        // openvpn@name instances and sits at "active (exited)" on every host
        // that merely has the package installed. Found on a real host, which
        // was showing "OpenVPN connected" with no tunnel anywhere.
        $out = '##LL:netbird
__absent__
##LL:netbird_unit
LoadState=not-found
'
            .'##LL:wg
__absent__
'
            .'##LL:ovpn_units
openvpn.service loaded active exited OpenVPN service
'
            .'##LL:ovpn_conf

##LL:links
lo
eth0
##LL:end
';

        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        $this->assertSame([], $result['providers']);
    }

    #[Test]
    public function an_openvpn_instance_that_ran_and_finished_is_not_connected(): void
    {
        $out = '##LL:netbird
__absent__
##LL:netbird_unit
LoadState=not-found
'
            .'##LL:wg
__absent__
'
            .'##LL:ovpn_units
openvpn@office.service loaded active exited OpenVPN office
'
            .'##LL:ovpn_conf
/etc/openvpn/office.conf
##LL:links
lo
eth0
##LL:end
';

        $result = (new VpnInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server(User::factory()->create()));

        $ovpn = $result['providers'][0];
        $this->assertSame('openvpn', $ovpn['id']);
        // The instance exists, so the provider is reported -- but "exited" is a
        // unit that finished, not a tunnel that is up.
        $this->assertFalse($ovpn['connected']);
        $this->assertSame('office', $ovpn['facts']['configs']);
    }

    #[Test]
    public function an_unknown_provider_never_reaches_the_host(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = new VpnRecordingProbe;
        $this->swap(ServerProbe::class, $probe);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/vpn/action", ['provider' => 'sneaky; rm -rf /', 'action' => 'down'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_selection');

        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function taking_the_network_down_is_audited_before_it_is_sent(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        // The command takes the link with it, so nothing comes back. That is
        // the effect, not a failure -- and the trail has to exist regardless.
        $this->swap(ServerProbe::class, new VpnRecordingProbe([['ok' => false, 'out' => '']]));

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/vpn/action", ['provider' => 'netbird', 'action' => 'down'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, AuditLog::query()->where('action', 'server.vpn_action')->count());
    }

    #[Test]
    public function it_is_owner_scoped(): void
    {
        $server = $this->server(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/vpn")
            ->assertNotFound();
    }
}
