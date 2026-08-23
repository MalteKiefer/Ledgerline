<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Servers\PanelInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hosting control panels on a monitored host.
 *
 * The properties that matter are all about not overclaiming: a missing section
 * is not a panel, a documented default port is not an open port, a count that
 * could not be read is not zero, and a port a panel usually uses is a lead
 * rather than a detection.
 */
class ServerPanelTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Server
    {
        return Server::forceCreate([
            'user_id' => User::factory()->create()->id,
            'name' => 'Web',
            'host' => 'web.example',
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'x'],
            'host_key' => 'ssh-ed25519 AAAA test',
            'host_fingerprint' => 'SHA256:test',
            'enabled' => true,
        ]);
    }

    /**
     * @return array{ok:bool,panels:list<array<string,mixed>>,candidates:list<array<string,string|int|null>>,error:string|null}
     */
    private function inspect(string $out): array
    {
        return (new PanelInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))
            ->inspect($this->server());
    }

    #[Test]
    public function it_reads_plesk_with_its_version_counts_and_unit(): void
    {
        $out = "##LL:plesk\nProduct version: Plesk Obsidian 18.0.62\nOS version:      Debian 12\nBuild date:      2026/01/09 12:00\n"
            ."##LL:plesk_counts\n49\t32\t5\t6\t0\t20\t196.4\n"
            ."##LL:units\npsa.service active running\n"
            ."##LL:listen\nLISTEN 0 128 0.0.0.0:8443 0.0.0.0:* users:((\"sw-cp-server\",pid=900,fd=6))\n";

        $result = $this->inspect($out);

        $this->assertCount(1, $result['panels']);
        $plesk = $result['panels'][0];
        $this->assertSame('plesk', $plesk['id']);
        $this->assertSame('Plesk Obsidian 18.0.62', $plesk['version']);
        $this->assertSame(
            ['domains' => 49, 'domains_active' => 32, 'subscriptions' => 5, 'customers' => 6, 'mailboxes' => 0, 'databases' => 20],
            $plesk['counts'],
        );
        $this->assertSame('psa.service', $plesk['unit']);
        $this->assertTrue($plesk['running']);
        $this->assertSame([8443], $plesk['ports']);
        $this->assertSame('Debian 12', $plesk['facts']['os']);

        // The port is claimed, so it must not also appear as an unexplained lead.
        $this->assertSame([], $result['candidates']);
    }

    #[Test]
    public function plesk_reports_the_sites_it_runs_and_which_php_they_are_on(): void
    {
        $out = "##LL:plesk\nProduct version: Plesk Obsidian 18.0.80.3\n"
            ."##LL:plesk_counts\n49\t32\t5\t6\t0\t20\t196.4\n"
            ."##LL:plesk_php\nplesk-php82-fpm-dedicated\t35\nplesk-php74-fpm-dedicated\t8\n"
            ."##LL:plesk_domains\n"
            ."big.example\t0\ttrue\tplesk-php82-fpm-dedicated\t114723\n"
            ."old.example\t2\tfalse\tplesk-php74-fpm-dedicated\t120\n"
            ."##LL:plesk_clients\nLemmer GmbH\t0\t25\n"
            ."##LL:plesk_ext\nfirewall - Firewall\ngit - Git\n"
            ."##LL:units\npsa.service active running\n##LL:listen\n";

        $plesk = $this->inspect($out)['panels'][0];
        $details = $plesk['details'];

        // The version is what turns into work: a handler out of support is not
        // a detail, and the count says how much of the machine it is.
        $this->assertSame([
            ['handler' => 'plesk-php82-fpm-dedicated', 'version' => '8.2', 'count' => 35],
            ['handler' => 'plesk-php74-fpm-dedicated', 'version' => '7.4', 'count' => 8],
        ], $details['php']);

        $this->assertSame('big.example', $details['domains'][0]['name']);
        $this->assertTrue($details['domains'][0]['active']);
        $this->assertTrue($details['domains'][0]['ssl']);
        $this->assertSame(114723, $details['domains'][0]['size_mb']);

        // Plesk's status is a bitmask where only zero is unambiguous, so
        // anything else is reported as "not active" rather than guessed at.
        $this->assertFalse($details['domains'][1]['active']);
        $this->assertFalse($details['domains'][1]['ssl']);
        $this->assertSame('7.4', $details['domains'][1]['php']);

        $this->assertSame([['name' => 'Lemmer GmbH', 'active' => true, 'domains' => 25]], $details['clients']);
        $this->assertSame(['Firewall', 'Git'], $details['extensions']);
        $this->assertSame(196.4, $details['disk_gb']);
    }

    #[Test]
    public function a_panel_that_is_not_plesk_carries_no_plesk_detail(): void
    {
        $out = "##LL:cockpit\nVersion: 320\n##LL:plesk\n__absent__\n##LL:units\n##LL:listen\n";

        $this->assertSame([], $this->inspect($out)['panels'][0]['details']);
    }

    #[Test]
    public function plesk_reports_its_addresses_databases_and_named_settings(): void
    {
        $out = "##LL:plesk\nProduct version: Plesk Obsidian 18.0.80.3\n"
            ."##LL:plesk_ips\n"
            ."85.215.107.154\t255.255.255.255\teth0\t\ttrue\n"
            ."10.5.226.22\t255.255.240.0\teth0\t85.215.107.154\tfalse\n"
            // plesk db prints an empty column as the word NULL, and letting that
            // through would claim the host is reachable at "NULL".
            .'172.17.0.1	255.255.0.0	docker0	NULL	false
'
            ."##LL:plesk_dbs\nk10166_pat21\tpostgresql\tpat21.de\nlonely\tmysql\t\n"
            ."##LL:plesk_settings\nadmin_email\tkontakt@example.de\nadmin_locale\tde-DE\n"
            ."##LL:units\n##LL:listen\n";

        $details = $this->inspect($out)['panels'][0]['details'];

        $this->assertSame('85.215.107.154', $details['ips'][0]['address']);
        $this->assertTrue($details['ips'][0]['main']);
        $this->assertNull($details['ips'][0]['public']);
        // Behind NAT the address the world uses is not on any local interface,
        // so it is shown next to the local one rather than instead of it.
        $this->assertSame('85.215.107.154', $details['ips'][1]['public']);
        $this->assertNull($details['ips'][2]['public']);

        $this->assertSame(['name' => 'k10166_pat21', 'type' => 'postgresql', 'domain' => 'pat21.de'], $details['databases'][0]);
        $this->assertNull($details['databases'][1]['domain']);

        $this->assertSame(['admin_email' => 'kontakt@example.de', 'admin_locale' => 'de-DE'], $details['settings']);
    }

    #[Test]
    public function the_settings_query_never_asks_for_the_admin_password(): void
    {
        $probe = new VpnRecordingProbe([['ok' => true, 'out' => "##LL:units\n"]]);
        (new PanelInspector($probe))->inspect($this->server());

        // `misc` holds admin_password_encrypted, so the query names the keys it
        // wants; a select for whatever is there would eventually take that too.
        $this->assertStringContainsString("m.param in ('admin_email'", $probe->sent());
        $this->assertStringNotContainsString('password', $probe->sent());
    }

    #[Test]
    public function a_site_action_outside_the_fixed_set_never_reaches_the_host(): void
    {
        $probe = new VpnRecordingProbe;
        $inspector = new PanelInspector($probe);
        $server = $this->server();

        foreach (['remove', 'create', 'exec', ''] as $verb) {
            $this->assertSame('invalid_selection', $inspector->siteAction($server, 'example.com', $verb)['error'], $verb);
        }

        // Nor does a name that is not a domain name.
        foreach (['a;rm -rf /', 'foo bar', '-flag', "x\nnewline"] as $name) {
            $this->assertSame('invalid_selection', $inspector->siteAction($server, $name, 'off')['error'], $name);
        }

        $this->assertSame('', $probe->sent());
    }

    #[Test]
    public function a_site_action_sends_the_fixed_command_and_reads_its_exit_code(): void
    {
        $probe = new VpnRecordingProbe([['ok' => true, 'out' => "SUCCESS: Disabling of domain 'shop.example' complete\n##LL:rc=0\n"]]);

        $result = (new PanelInspector($probe))->siteAction($this->server(), 'shop.example', 'off');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Disabling of domain', $result['output']);
        // The marker is plumbing and has no business in what a person reads.
        $this->assertStringNotContainsString('##LL:rc', $result['output']);
        $this->assertStringContainsString("plesk bin site --off 'shop.example'", $probe->sent());
    }

    #[Test]
    public function a_site_action_on_a_host_without_the_panel_says_so(): void
    {
        $probe = new VpnRecordingProbe([['ok' => false, 'out' => "__no_plesk__\n"]]);

        $result = (new PanelInspector($probe))->siteAction($this->server(), 'shop.example', 'on');

        // Not "unreachable": the host answered, it simply has no Plesk.
        $this->assertSame('not_installed', $result['error']);
    }

    #[Test]
    public function an_absent_section_is_not_a_panel(): void
    {
        $sections = ['plesk', 'cpanel', 'directadmin', 'ispconfig', 'webmin', 'virtualmin', 'cyberpanel',
            'hestia', 'vesta', 'aapanel', 'cloudpanel', 'froxlor', 'keyhelp', 'cockpit', 'runcloud', 'serverpilot'];

        $out = '';
        foreach ($sections as $section) {
            $out .= "##LL:{$section}\n__absent__\n";
        }
        // A section that is simply empty must count the same way, or one command
        // printing nothing would invent a panel out of silence.
        $out .= "##LL:units\n\n##LL:containers\n\n##LL:listen\n";

        $result = $this->inspect($out);

        $this->assertSame([], $result['panels']);
    }

    #[Test]
    public function a_stopped_panel_is_told_apart_from_one_with_no_unit(): void
    {
        $out = "##LL:hestia\nVERSION='1.9.2'\n"
            ."##LL:hestia_users\n4\n"
            ."##LL:ispconfig\n\$conf['app_version'] = 'x'; define('ISPC_APP_VERSION', '3.2.11p1');\n"
            ."##LL:units\nhestia.service inactive dead\n"
            ."##LL:listen\n";

        $result = $this->inspect($out);

        $hestia = collect($result['panels'])->firstWhere('id', 'hestia');
        $this->assertNotNull($hestia);
        $this->assertFalse($hestia['running']);
        $this->assertSame('1.9.2', $hestia['version']);
        $this->assertSame(['users' => 4], $hestia['counts']);

        // No unit to ask is its own answer, not "stopped".
        $ispconfig = collect($result['panels'])->firstWhere('id', 'ispconfig');
        $this->assertNotNull($ispconfig);
        $this->assertNull($ispconfig['running']);
        $this->assertSame('3.2.11p1', $ispconfig['version']);
    }

    #[Test]
    public function a_default_port_is_not_reported_unless_something_listens_on_it(): void
    {
        $out = "##LL:cockpit\nVersion: 320\n##LL:units\ncockpit.service active running\n##LL:listen\n";

        $panel = $this->inspect($out)['panels'][0];

        $this->assertSame('cockpit', $panel['id']);
        $this->assertSame([], $panel['ports']);
    }

    #[Test]
    public function an_unreadable_count_is_left_out_rather_than_reported_as_zero(): void
    {
        $out = "##LL:cpanel\n11.126.0.15\n##LL:cpanel_users\n__absent__\n##LL:units\n##LL:listen\n";

        $panel = $this->inspect($out)['panels'][0];

        $this->assertSame('cpanel', $panel['id']);
        $this->assertSame([], $panel['counts']);
    }

    #[Test]
    public function virtualmin_replaces_webmin_rather_than_being_counted_twice(): void
    {
        $out = "##LL:webmin\n2.202\n##LL:webmin_conf\nport=10000\nssl=1\n"
            ."##LL:virtualmin\n7.30.0\n##LL:virtualmin_domains\n6\n"
            ."##LL:units\nwebmin.service active running\n##LL:listen\n";

        $result = $this->inspect($out);

        $this->assertCount(1, $result['panels']);
        $panel = $result['panels'][0];
        $this->assertSame('virtualmin', $panel['id']);
        $this->assertSame('7.30.0', $panel['version']);
        $this->assertSame('2.202', $panel['facts']['webmin']);
        $this->assertSame('on', $panel['facts']['tls']);
        $this->assertSame([10000], $panel['ports']);
        $this->assertSame(['domains' => 6], $panel['counts']);
    }

    #[Test]
    public function a_container_panel_is_recognised_by_image_not_by_name(): void
    {
        $out = "##LL:containers\n"
            ."plesk\tnginx:1.27\t0.0.0.0:80->80/tcp\n"
            ."web-admin\tportainer/portainer-ce:2.21.4\t0.0.0.0:9443->9443/tcp\n"
            ."##LL:units\n##LL:listen\n";

        $result = $this->inspect($out);

        // A container called "plesk" running nginx is a web server.
        $this->assertCount(1, $result['panels']);
        $panel = $result['panels'][0];
        $this->assertSame('Portainer', $panel['name']);
        $this->assertSame('2.21.4', $panel['version']);
        $this->assertSame('web-admin', $panel['container']);
        $this->assertSame([9443], $panel['ports']);
    }

    #[Test]
    public function an_unclaimed_panel_port_is_a_lead_and_says_so(): void
    {
        $out = "##LL:units\n##LL:listen\n"
            ."LISTEN 0 511 0.0.0.0:2083 0.0.0.0:* users:((\"nginx\",pid=12,fd=8))\n"
            ."LISTEN 0 128 127.0.0.1:5432 0.0.0.0:* users:((\"postgres\",pid=44,fd=5))\n";

        $result = $this->inspect($out);

        $this->assertSame([], $result['panels']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame(2083, $result['candidates'][0]['port']);
        $this->assertSame('nginx', $result['candidates'][0]['process']);
        $this->assertSame('cPanel', $result['candidates'][0]['hint']);
    }

    #[Test]
    public function the_ssh_port_it_arrived_on_is_not_offered_as_a_lead(): void
    {
        // 2222 is DirectAdmin's port and also, here, the door this very
        // connection came through. Reporting it teaches people to ignore the list.
        $out = '##LL:units
##LL:listen
'
            .'LISTEN 0 128 0.0.0.0:2222 0.0.0.0:* users:(("sshd",pid=7,fd=3))
'
            .'LISTEN 0 128 0.0.0.0:10000 0.0.0.0:* users:(("miniserv.pl",pid=8,fd=4))
';

        $server = $this->server();
        $server->forceFill(['port' => 2222])->save();

        $result = (new PanelInspector(new VpnRecordingProbe([['ok' => true, 'out' => $out]])))->inspect($server);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame(10000, $result['candidates'][0]['port']);
    }

    #[Test]
    public function it_never_asks_a_panel_for_a_working_login(): void
    {
        $probe = new VpnRecordingProbe([['ok' => true, 'out' => "##LL:units\n"]]);
        (new PanelInspector($probe))->inspect($this->server());

        // Both of these print credentials that grant access on the spot.
        $this->assertStringNotContainsString('bt default', $probe->sent());
        $this->assertStringNotContainsString('get-login-link', $probe->sent());
    }

    #[Test]
    public function an_unreachable_host_says_so_instead_of_reporting_no_panels(): void
    {
        $result = (new PanelInspector(new VpnRecordingProbe([['ok' => false, 'out' => '']])))
            ->inspect($this->server());

        $this->assertFalse($result['ok']);
        $this->assertSame('unreachable', $result['error']);
    }
}
