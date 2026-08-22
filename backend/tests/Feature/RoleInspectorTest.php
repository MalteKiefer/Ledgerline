<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerFact;
use App\Models\User;
use App\Services\Servers\RoleInspector;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A probe that answers from a queue and keeps the script it was handed. */
class RoleRecordingProbe extends ServerProbe
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
 * The figures that only matter for what a machine actually is.
 *
 * Two properties carry the risk. The host is only ever asked about roles the
 * stored snapshot says it has, so a caller cannot make us run the Proxmox
 * section against a mail server. And an empty queue must read as zero, not as
 * "we could not find out": those look alike on screen and mean the opposite.
 */
class RoleInspectorTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $roles */
    private function server(User $owner, array $roles): Server
    {
        $server = Server::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Mail',
            'host' => 'mail.example',
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'x'],
            'host_key' => 'ssh-ed25519 AAAA test',
            'host_fingerprint' => 'SHA256:test',
            'enabled' => true,
        ]);

        ServerFact::forceCreate([
            'server_id' => $server->id,
            'facts' => ['role' => ['roles' => $roles]],
            'collected_at' => now(),
        ]);

        return $server;
    }

    #[Test]
    public function it_reads_a_mail_server_queue_spam_share_and_sessions(): void
    {
        $probe = new RoleRecordingProbe([['ok' => true, 'out' => implode("\n", [
            '##LL:mail_queue',
            '-- 13953 Kbytes in 3855 Requests.',
            '##LL:mail_rspamd',
            'Messages scanned: 2079',
            'Messages treated as spam: 31 (1.49%)',
            'Messages treated as ham: 2048 (98.51%)',
            'Messages learned: 11340',
            '##LL:mail_dovecot',
            'malte@example.com 1 imap (10.0.0.2)',
            'tina@example.com 2 imap (10.0.0.3)',
            '##LL:end',
        ])]]);

        $out = (new RoleInspector($probe))->inspect($this->server(User::factory()->create(), ['mail']), ['mail']);

        $this->assertSame(3855, $out['mail']['queued']);
        $this->assertSame(2079, $out['mail']['rspamd']['scanned']);
        $this->assertSame(31, $out['mail']['rspamd']['treated_as_spam']);
        $this->assertCount(2, $out['mail']['sessions']);
        $this->assertSame('malte@example.com', $out['mail']['sessions'][0]['user']);

        // Nothing about virtualisation was asked, so nothing is claimed.
        $this->assertNull($out['guests']);
        $this->assertStringNotContainsString('qm list', $probe->sent());
    }

    #[Test]
    public function an_empty_queue_reads_as_zero_and_an_absent_one_as_unknown(): void
    {
        $empty = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => "##LL:mail_queue\nMail queue is empty\n##LL:end\n"]])))
            ->inspect($this->server(User::factory()->create(), ['mail']), ['mail']);
        $this->assertSame(0, $empty['mail']['queued']);

        // No postfix on the box: we did not find zero mail, we found no queue.
        $absent = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => "##LL:mail_queue\n__absent__\n##LL:end\n"]])))
            ->inspect($this->server(User::factory()->create(), ['mail']), ['mail']);
        $this->assertNull($absent['mail']['queued']);
        $this->assertNull($absent['mail']['queue_raw']);
    }

    #[Test]
    public function it_lists_proxmox_guests_and_libvirt_domains(): void
    {
        $out = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => implode("\n", [
            '##LL:pve_vm',
            '       101 web-01               running    4096',
            '       102 db-01                stopped    8192',
            '##LL:pve_ct',
            '       201 mail-ct              running    2048',
            '##LL:libvirt',
            ' 3    builder                        running',
            '##LL:end',
        ])]])))->inspect($this->server(User::factory()->create(), ['virtualisation']), ['virtualisation']);

        $this->assertCount(4, $out['guests']);
        $this->assertSame(['kind' => 'qemu', 'id' => '101', 'name' => 'web-01', 'status' => 'running'], $out['guests'][0]);
        $this->assertSame('lxc', $out['guests'][2]['kind']);
        $this->assertSame('libvirt', $out['guests'][3]['kind']);
    }

    #[Test]
    public function it_reads_database_sizes_and_connections(): void
    {
        $out = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => implode("\n", [
            '##LL:pg',
            'ledgerline|84934656|7',
            'postgres|8552435|1',
            '##LL:mysql',
            '__absent__',
            '##LL:redis',
            'used_memory_human:1.42M',
            '##LL:end',
        ])]])))->inspect($this->server(User::factory()->create(), ['database']), ['database']);

        $this->assertSame('postgres', $out['databases'][0]['engine']);
        $this->assertSame(84934656, $out['databases'][0]['size_b']);
        $this->assertSame(7, $out['databases'][0]['connections']);
        $this->assertSame('redis', $out['databases'][2]['engine']);
        $this->assertSame('1.42M', $out['databases'][2]['used']);
    }

    #[Test]
    public function it_asks_the_host_nothing_when_no_role_applies(): void
    {
        $probe = new RoleRecordingProbe;
        $out = (new RoleInspector($probe))->inspect($this->server(User::factory()->create(), []), ['storage']);

        $this->assertTrue($out['ok']);
        $this->assertSame([], $probe->scripts);
        $this->assertNull($out['mail']);
    }

    #[Test]
    public function the_endpoint_takes_the_roles_from_the_snapshot_not_from_the_caller(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user, ['mail']);

        $probe = new RoleRecordingProbe([['ok' => true, 'out' => "##LL:mail_queue\nMail queue is empty\n##LL:end\n"]]);
        $this->swap(ServerProbe::class, $probe);

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/role-details?roles[]=virtualisation")
            ->assertOk()
            ->assertJsonPath('mail.queued', 0)
            ->assertJsonPath('guests', null);

        $this->assertStringNotContainsString('qm list', $probe->sent());
    }

    #[Test]
    public function a_role_whose_tools_live_in_containers_reads_as_unreadable_not_as_empty(): void
    {
        // A Docker host: the role was detected from the container images, but
        // psql and caddy are not on the host, so nobody could look. An empty
        // list here would claim there are no databases.
        $out = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => implode('
', [
            '##LL:pg',
            '__absent__',
            '##LL:mysql',
            '__absent__',
            '##LL:redis',
            '__absent__',
            '##LL:web_sites',
            '__absent__',
            '##LL:end',
        ])]])))->inspect($this->server(User::factory()->create(), ['database', 'web']), ['database', 'web']);

        $this->assertNull($out['databases']);
        $this->assertNull($out['sites']);
        $this->assertSame(['database', 'web'], $out['unreadable']);
    }

    #[Test]
    public function a_role_that_was_read_and_had_nothing_is_not_called_unreadable(): void
    {
        $out = (new RoleInspector(new RoleRecordingProbe([['ok' => true, 'out' => '##LL:web_sites

##LL:end
']])))
            ->inspect($this->server(User::factory()->create(), ['web']), ['web']);

        // Caddy answered with no domains. That is an answer, not a gap -- but
        // an empty section is indistinguishable from an absent tool here, so
        // this documents which way the doubt falls.
        $this->assertSame(['web'], $out['unreadable']);
        $this->assertNull($out['sites']);
    }

    #[Test]
    public function it_is_owner_scoped(): void
    {
        $server = $this->server(User::factory()->create(), ['mail']);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/servers/{$server->id}/role-details")
            ->assertNotFound();
    }
}
