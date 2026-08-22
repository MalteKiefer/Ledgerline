<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ApplyServerUpdates;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\DiskUsageInspector;
use App\Services\Servers\PackageManager;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A probe that answers from a queue and keeps every script it was handed.
 *
 * Keeping the scripts is the point of several tests below: what protects the
 * host is the shape of the command we send, and the only honest way to check
 * that is to read the command rather than the result it produced.
 */
class MaintenanceRecordingProbe extends ServerProbe
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
 * Maintenance: what is using the space, and what needs updating.
 *
 * Two properties carry most of the risk here. "Security" must come from the
 * origin the package would be installed from, never from what the package is
 * called — a package named security-tools out of an ordinary repository is a
 * normal update, and colouring it red teaches the owner to ignore the colour.
 * And a path from a request must be resolved before it is quoted, so that no
 * command we send ever contains a traversal the target's shell could act on.
 */
class ServerMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    /** The two lines a real Debian host produced, one security, one not. */
    private const APT_REAL = <<<'OUT'
    Inst docker-ce-cli [5:29.7.1-1~debian.13~trixie] (5:29.7.2-1~debian.13~trixie Docker CE:trixie [amd64])
    Inst linux-image-amd64 [6.12.0] (6.12.1 Debian:13/stable-security [amd64])
    OUT;

    /** What `du -x -k --max-depth=1 /var` actually prints, total last. */
    private const DU_REAL = "2214990\t/var/lib\n41200\t/var/log\n2260000\t/var\n";

    // ---------------------------------------------------------------- disk

    #[Test]
    public function the_total_for_the_queried_path_is_not_reported_as_a_finding(): void
    {
        $probe = new MaintenanceRecordingProbe([['ok' => true, 'out' => self::DU_REAL]]);

        $result = (new DiskUsageInspector($probe))->inspect($this->server(User::factory()->create()), '/var');

        $this->assertTrue($result['ok']);
        // /var is the thing being broken down, not one of the pieces.
        $this->assertSame(
            [
                ['path' => '/var/lib', 'size_kb' => 2214990],
                ['path' => '/var/log', 'size_kb' => 41200],
            ],
            $result['entries'],
        );
    }

    #[Test]
    public function a_path_with_a_control_character_is_refused_and_nothing_is_sent(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->inspectorProbe();

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/disk-usage?path=".rawurlencode("/var/log\nrm -rf /"))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_path');

        // The important half: the rejection happens here, so the host never
        // sees the string at all.
        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function a_relative_path_never_reaches_the_host(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $probe = $this->inspectorProbe();

        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/disk-usage?path=var/log")
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_path');

        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function a_traversal_is_resolved_here_so_the_shell_never_sees_one(): void
    {
        $probe = new MaintenanceRecordingProbe([['ok' => true, 'out' => '']]);

        $result = (new DiskUsageInspector($probe))->inspect($this->server(User::factory()->create()), '/var/log/../../etc');

        // Note the shape of this guarantee: the segments are collapsed rather
        // than the request refused. There is no confined root to escape from —
        // du over an absolute path is the whole feature — so what matters is
        // that ".." is resolved before quoting and cannot be re-interpreted by
        // anything downstream.
        $this->assertSame('/etc', $result['path']);
        $this->assertStringContainsString("'/etc'", $probe->sent());
        $this->assertStringNotContainsString('..', $probe->sent());
    }

    #[Test]
    public function the_command_stays_on_one_filesystem_is_time_bounded_and_quotes_the_path(): void
    {
        $probe = new MaintenanceRecordingProbe([['ok' => true, 'out' => '']]);

        (new DiskUsageInspector($probe))->inspect($this->server(User::factory()->create()), '/var/lib/my dir');

        $sent = $probe->sent();
        // -x: without it a du of / on a container host walks every overlay
        // mount and counts the same bytes many times over.
        $this->assertStringContainsString('-x', $sent);
        // timeout on the target too: otherwise the far side keeps walking the
        // tree long after we stopped listening.
        $this->assertStringContainsString('timeout ', $sent);
        // POSIX single quotes, so a space in a directory name is one argument.
        $this->assertStringContainsString("'/var/lib/my dir'", $sent);
    }

    #[Test]
    public function an_unreachable_host_is_said_plainly(): void
    {
        $probe = new MaintenanceRecordingProbe([['ok' => false, 'out' => '']]);

        $result = (new DiskUsageInspector($probe))->inspect($this->server(User::factory()->create()), '/var');

        $this->assertFalse($result['ok']);
        $this->assertSame('unreachable', $result['error']);
        $this->assertSame([], $result['entries']);
    }

    // ------------------------------------------------------------- updates

    #[Test]
    public function security_comes_from_the_origin_in_brackets_not_from_the_package_name(): void
    {
        // The line that matters: a package whose NAME reads like a security
        // tool, offered from an ordinary vendor repository.
        $listing = self::APT_REAL."\n"
            .'Inst security-tools [1.0] (1.1 Docker CE:trixie [amd64])';

        $result = $this->pending($this->aptOutput($listing));

        $byName = collect($result['packages'])->keyBy('name');

        $this->assertTrue($byName['linux-image-amd64']['security'], 'Debian:13/stable-security is a security origin');
        $this->assertFalse($byName['docker-ce-cli']['security'], 'Docker CE:trixie is not');
        // Guessing from the name would mark this red every month for no reason.
        $this->assertFalse($byName['security-tools']['security'], 'the name says nothing about the origin');
    }

    #[Test]
    public function the_apt_listing_keeps_both_versions_of_each_package(): void
    {
        $result = $this->pending($this->aptOutput(self::APT_REAL));

        $this->assertTrue($result['ok']);
        $this->assertSame('apt', $result['kind']);
        $this->assertCount(2, $result['packages']);

        $docker = collect($result['packages'])->firstWhere('name', 'docker-ce-cli');
        $this->assertSame('5:29.7.1-1~debian.13~trixie', $docker['current']);
        $this->assertSame('5:29.7.2-1~debian.13~trixie', $docker['version']);
    }

    #[Test]
    public function security_relevant_packages_are_listed_first(): void
    {
        $result = $this->pending($this->aptOutput(self::APT_REAL));

        // Somebody deciding whether this can wait until the weekend reads the
        // top of the list, so that is where the answer has to be.
        $this->assertSame('linux-image-amd64', $result['packages'][0]['name']);
        $this->assertTrue($result['packages'][0]['security']);
    }

    #[Test]
    public function alpine_is_parsed_and_nothing_there_is_claimed_to_be_security(): void
    {
        $out = "##LL:kind\napk\n##LL:list\nzlib-1.2.13-r0 < 1.3-r0\nbusybox-1.36.1-r5 < 1.36.1-r7\n##LL:end\n";

        $result = $this->pending($out);

        $this->assertSame('apk', $result['kind']);
        $this->assertCount(2, $result['packages']);
        // apk glues the installed version onto the name in this output; the two
        // are separated so the row reads the same way an apt row does.
        $zlib = collect($result['packages'])->firstWhere('name', 'zlib');
        $this->assertNotNull($zlib, 'the version must not be left stuck to the name');
        $this->assertSame('1.2.13-r0', $zlib['current']);
        $this->assertSame('1.3-r0', $zlib['version']);
        // apk's output does not say which of these close a hole. Saying nothing
        // is the honest answer; guessing would be worse than silence.
        $this->assertSame([], array_values(array_filter($result['packages'], static fn (array $p): bool => $p['security'])));
    }

    #[Test]
    public function a_host_without_a_package_manager_is_not_an_error(): void
    {
        $result = $this->pending("##LL:kind\nnone\n##LL:end\n");

        $this->assertTrue($result['ok'], 'nothing is wrong with this machine');
        $this->assertSame('none', $result['kind']);
        $this->assertSame([], $result['packages']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function an_unreachable_host_is_distinct_from_having_no_updates(): void
    {
        $probe = new MaintenanceRecordingProbe([['ok' => false, 'out' => '']]);

        $result = (new PackageManager($probe))->pending($this->server(User::factory()->create()));

        $this->assertFalse($result['ok']);
        $this->assertSame('unreachable', $result['error']);
        $this->assertSame([], $result['packages']);
    }

    // ---------------------------------------------------------- controller

    #[Test]
    public function every_endpoint_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->server($owner);
        $probe = $this->inspectorProbe();
        Queue::fake();

        // 404 rather than 403 throughout: a stranger learns nothing about
        // whether this id exists.
        $this->actingAs($stranger)->getJson("/api/v1/servers/{$server->id}/disk-usage?path=/var")->assertNotFound();
        $this->actingAs($stranger)->getJson("/api/v1/servers/{$server->id}/updates")->assertNotFound();
        $this->actingAs($stranger)->postJson("/api/v1/servers/{$server->id}/updates")->assertNotFound();

        $this->assertSame([], $probe->scripts);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function applying_updates_answers_immediately_and_hands_the_work_to_a_worker(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/updates")
            ->assertStatus(202)
            ->assertJsonPath('queued', true)
            ->assertHeader('Cache-Control', 'no-store, private');

        Queue::assertPushed(ApplyServerUpdates::class, fn (ApplyServerUpdates $job): bool => $job->serverId === $server->id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'server.updates_applied',
        ]);
    }

    #[Test]
    public function the_audit_entry_is_written_before_the_job_is_queued(): void
    {
        // A machine that does not come back from an upgrade should still have
        // left a record that one was started — which only holds if the record
        // is written first.
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $server = $this->server($user);

        $auditedAtQueueTime = null;
        Event::listen(JobQueued::class, function () use (&$auditedAtQueueTime): void {
            $auditedAtQueueTime = AuditLog::query()->where('action', 'server.updates_applied')->count();
        });

        $this->actingAs($user)->postJson("/api/v1/servers/{$server->id}/updates")->assertStatus(202);

        $this->assertSame(1, $auditedAtQueueTime, 'the audit row must already exist when the job is queued');
    }

    // ----------------------------------------------------------------- job

    #[Test]
    public function a_finished_upgrade_reports_the_tail_of_what_the_host_said(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $server = $this->server($user);
        $output = "Reading package lists...\nSetting up linux-image-amd64 (6.12.1)\ndone.";

        (new ApplyServerUpdates($server->id))->handle($this->packagesReturning('apt', true, $output));

        $notification = AppNotification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('info', $notification->level);
        $this->assertSame('server', $notification->category);
        // The host's own last lines, not a paraphrase: an upgrade says why it
        // went the way it did in exactly those lines.
        $this->assertStringContainsString('Setting up linux-image-amd64', (string) $notification->body);
        $this->assertRealText((string) $notification->title);
    }

    #[Test]
    public function a_failed_upgrade_is_a_warning_and_still_carries_the_reason(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $server = $this->server($user);
        $output = 'E: Could not get lock /var/lib/dpkg/lock-frontend';

        (new ApplyServerUpdates($server->id))->handle($this->packagesReturning('apt', false, $output));

        $notification = AppNotification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('warning', $notification->level);
        $this->assertStringContainsString('Could not get lock', (string) $notification->body);
        $this->assertRealText((string) $notification->title);
    }

    // ------------------------------------------------------------- helpers

    /** A title is for a human to read; a raw key means the message never landed. */
    private function assertRealText(string $title): void
    {
        $this->assertStringNotContainsString(
            'servers.notify.',
            $title,
            'the notification title is an untranslated key — servers.notify.updates_done / updates_failed are missing from lang/',
        );
    }

    /** @param  array{ok:bool,out:string}  ...$results */
    private function packagesReturning(string $kind, bool $ok, string $output): PackageManager
    {
        // Two calls: the detection probe, then the upgrade itself.
        return new PackageManager(new MaintenanceRecordingProbe([
            ['ok' => true, 'out' => $kind."\n"],
            ['ok' => $ok, 'out' => $output],
        ]));
    }

    /** @return array{ok:bool,kind:string,packages:list<array{name:string,version:string,current:string,security:bool}>,error:string|null} */
    private function pending(string $output): array
    {
        return (new PackageManager(new MaintenanceRecordingProbe([['ok' => true, 'out' => $output]])))
            ->pending($this->server(User::factory()->create()));
    }

    private function aptOutput(string $listing): string
    {
        return "##LL:kind\napt\n##LL:list\n".$listing."\n##LL:end\n";
    }

    /** Binds a recording inspector so a rejected request can be shown to send nothing. */
    private function inspectorProbe(): MaintenanceRecordingProbe
    {
        $probe = new MaintenanceRecordingProbe([['ok' => true, 'out' => '']]);
        $this->swap(DiskUsageInspector::class, new DiskUsageInspector($probe));

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
