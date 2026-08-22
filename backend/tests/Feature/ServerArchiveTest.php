<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\RemoteArchiver;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use App\Services\Servers\SftpBrowser;
use App\Support\DiskTempFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A probe that answers with canned output and keeps every script it was given.
 *
 * The scripts are the contract worth asserting on: quoting, the tar flag per
 * format and the destination an archive unpacks into all show up there, and
 * none of them need a real host to be verified.
 */
class ArchiveRecordingProbe extends ServerProbe
{
    /** @var list<string> */
    public array $scripts = [];

    public function __construct(private string $output = '##LL:rc=0') {}

    public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false): array
    {
        $this->scripts[] = $script;

        return ['ok' => true, 'out' => $this->output, 'err' => '', 'exit' => 0];
    }

    /** Everything sent, joined, for a single containment assertion. */
    public function sent(): string
    {
        return implode("\n", $this->scripts);
    }
}

/** An SFTP client whose download hands back a file with known bytes. */
class ArchiveStubSftp extends SftpBrowser
{
    public bool $succeed = true;

    public string $payload = 'ARCHIVE-BYTES';

    public function __construct(ServerProbe $probe)
    {
        parent::__construct($probe);
    }

    public function download(Server $server, string $path): array
    {
        if (! $this->succeed) {
            return ['ok' => false, 'file' => null, 'error' => 'not_found'];
        }

        $file = DiskTempFile::create('ll-archive-test');
        file_put_contents($file->path(), $this->payload);

        return ['ok' => true, 'file' => $file, 'error' => null];
    }
}

class ServerArchiveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_known_suffix_maps_to_a_format_and_nothing_else_does(): void
    {
        $cases = [
            '/srv/a.tar.gz' => 'tar.gz',
            '/srv/a.tgz' => 'tar.gz',
            '/srv/a.tar.xz' => 'tar.xz',
            '/srv/a.txz' => 'tar.xz',
            '/srv/a.tar.bz2' => 'tar.bz2',
            '/srv/a.tbz2' => 'tar.bz2',
            '/srv/a.tar.zst' => 'tar.zst',
            '/srv/a.tar' => 'tar',
            '/srv/a.zip' => 'zip',
            '/srv/a.7z' => '7z',
            '/srv/a.rar' => 'rar',
            '/srv/a.gz' => 'gz',
            '/srv/a.xz' => 'xz',
            '/srv/a.bz2' => 'bz2',
            '/srv/a.zst' => 'zst',
            // Case is not a format signal on a host that writes BACKUP.TAR.GZ.
            '/srv/BACKUP.TAR.GZ' => 'tar.gz',
        ];

        foreach ($cases as $path => $expected) {
            $this->assertSame($expected, RemoteArchiver::formatOf($path), $path);
        }

        // Guessing is worse than refusing: an unpack aimed at the wrong tool
        // either fails loudly or, worse, writes something unexpected.
        $this->assertNull(RemoteArchiver::formatOf('/srv/notes.txt'));
        $this->assertNull(RemoteArchiver::formatOf('/srv/archive'));
        // The longer suffix wins, or a .tar.gz would be decompressed as a bare
        // gzip stream and lose every member name.
        $this->assertSame('tar.gz', RemoteArchiver::formatOf('/srv/a.tar.gz'));
    }

    #[Test]
    public function packing_the_root_is_refused_however_it_is_spelled(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));
        $server = $this->server(User::factory()->create());

        // Traversal is collapsed before anything is checked, so a path that
        // climbs out to / is caught here rather than reaching tar.
        $this->assertSame('invalid_path', $archiver->pack($server, ['/etc/ssh/../../..'])['error']);
        $this->assertSame('invalid_path', $archiver->pack($server, ['/'])['error']);
        // A relative path can never be expressed on the far side.
        $this->assertSame('invalid_path', $archiver->pack($server, ['srv/data'])['error']);
        // One bad entry poisons the whole selection rather than being skipped.
        $this->assertSame('invalid_path', $archiver->pack($server, ['/srv/ok', '/'])['error']);

        $this->assertSame([], $probe->scripts, 'nothing may reach the host once a path is refused');
    }

    #[Test]
    public function a_format_that_cannot_be_produced_never_reaches_the_host(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));
        $server = $this->server(User::factory()->create());

        // zip and 7z can be read back but not written, and offering a button
        // that fails on click is worse than not offering it.
        $this->assertSame('invalid_selection', $archiver->pack($server, ['/srv/data'], 'zip')['error']);
        $this->assertSame('invalid_selection', $archiver->pack($server, ['/srv/data'], '7z')['error']);
        $this->assertSame('invalid_selection', $archiver->pack($server, [], 'tar.gz')['error']);
        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function a_pack_runs_relative_to_the_shared_parent_and_cleans_up_after_itself(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->pack($this->server(User::factory()->create()), ['/srv/app/one', '/srv/app/two'], 'tar.xz');

        $this->assertTrue($result['ok']);
        $sent = $probe->sent();
        // -C the shared parent with bare member names, or the archive unpacks
        // into a chain of empty directories nobody asked for.
        $this->assertStringContainsString("tar -cJf '/tmp/ll-pack-", $sent);
        $this->assertStringContainsString("-C '/srv/app' -- 'one' 'two'", $sent);
        // The staged archive is removed whatever happened: somebody's files
        // left in /tmp are litter at best and a disclosure at worst.
        $this->assertStringContainsString("rm -f '/tmp/ll-pack-", $sent);
        $this->assertSame('app-files.tar.xz', $result['name']);
    }

    #[Test]
    public function a_single_selection_is_named_after_what_was_packed(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->pack($this->server(User::factory()->create()), ['/srv/app/logs'], 'tar.gz');

        $this->assertSame('logs.tar.gz', $result['name']);
        $this->assertStringContainsString("tar -czf '/tmp/ll-pack-", $probe->sent());
    }

    #[Test]
    public function a_failing_tar_reports_the_failure_and_still_removes_the_fragment(): void
    {
        $probe = new ArchiveRecordingProbe('tar: /srv/data: Permission denied
##LL:rc=2');
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->pack($this->server(User::factory()->create()), ['/srv/data']);

        $this->assertSame('pack_failed', $result['error']);
        $this->assertNull($result['file']);
        $this->assertStringContainsString('rm -f ', $probe->sent(), 'a half-written archive is not left behind');
    }

    #[Test]
    public function an_archive_we_cannot_name_is_refused_rather_than_guessed(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));
        $server = $this->server(User::factory()->create());

        $this->assertSame('unknown_format', $archiver->extract($server, '/srv/notes.txt')['error']);
        $this->assertSame('invalid_path', $archiver->extract($server, 'srv/a.tar.gz')['error']);
        // A destination that resolves to the filesystem root would spray an
        // archive over the whole machine, and there is no undo for that.
        $this->assertSame('invalid_path', $archiver->extract($server, '/srv/a.tar.gz', '/srv/../..')['error']);
        $this->assertSame([], $probe->scripts);
    }

    #[Test]
    public function an_archive_unpacks_beside_itself_not_into_the_current_directory(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->extract($this->server(User::factory()->create()), '/srv/app/backup.tar.gz');

        // Into its own directory: an archive that turns out to have no
        // top-level folder would otherwise scatter its contents over whatever
        // was already there.
        $this->assertTrue($result['ok']);
        $this->assertSame('/srv/app/backup', $result['dest']);
        $sent = $probe->sent();
        $this->assertStringContainsString("mkdir -p '/srv/app/backup'", $sent);
        $this->assertStringContainsString("tar -xzf '/srv/app/backup.tar.gz' -C '/srv/app/backup'", $sent);
    }

    #[Test]
    public function a_bare_compressed_file_decompresses_under_its_stripped_name(): void
    {
        $probe = new ArchiveRecordingProbe;
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->extract($this->server(User::factory()->create()), '/var/log/syslog.1.gz');

        // A bare gzip stream carries no member name, so the suffix is all we
        // have to go on.
        $this->assertSame('/var/log/syslog.1', $result['dest']);
        $this->assertStringContainsString("gzip -dc '/var/log/syslog.1.gz' > '/var/log/syslog.1/syslog.1'", $probe->sent());
    }

    #[Test]
    public function a_failing_extraction_is_reported_without_the_marker_leaking_into_the_output(): void
    {
        $probe = new ArchiveRecordingProbe("unzip: cannot find or open\n##LL:rc=9");
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $result = $archiver->extract($this->server(User::factory()->create()), '/srv/a.zip');

        $this->assertFalse($result['ok']);
        $this->assertSame('extract_failed', $result['error']);
        $this->assertSame('unzip: cannot find or open', $result['output']);
    }

    #[Test]
    public function only_formats_the_host_can_actually_handle_are_offered(): void
    {
        // A minimal host: tar and gzip, nothing else.
        $probe = new ArchiveRecordingProbe("tar\ngzip\n");
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $tools = $archiver->tools($this->server(User::factory()->create()));

        $this->assertTrue($tools['ok']);
        $this->assertSame(['tar', 'tar.gz'], $tools['pack']);
        // No xz, no zstd, no unzip: claiming those would produce buttons that
        // fail on click.
        $this->assertNotContains('tar.xz', $tools['pack']);
        $this->assertNotContains('zip', $tools['extract']);
        $this->assertNotContains('7z', $tools['extract']);
        $this->assertSame(['tar', 'tar.gz', 'gz'], $tools['extract']);
    }

    #[Test]
    public function extraction_only_formats_are_offered_for_extraction_alone(): void
    {
        $probe = new ArchiveRecordingProbe("tar\ngzip\nunzip\n7z\nunrar\n");
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $tools = $archiver->tools($this->server(User::factory()->create()));

        // Readable but not writable, and the two lists must not be conflated.
        $this->assertContains('zip', $tools['extract']);
        $this->assertContains('7z', $tools['extract']);
        $this->assertContains('rar', $tools['extract']);
        $this->assertNotContains('zip', $tools['pack']);
        $this->assertNotContains('rar', $tools['pack']);
    }

    #[Test]
    public function a_host_that_does_not_answer_offers_nothing(): void
    {
        $probe = new class extends ServerProbe
        {
            public function exec(ServerTarget $target, string $hostKey, string $script, bool $interactive = false): array
            {
                return ['ok' => false, 'out' => '', 'err' => 'timeout', 'exit' => 255];
            }
        };
        $archiver = new RemoteArchiver($probe, new ArchiveStubSftp($probe));

        $tools = $archiver->tools($this->server(User::factory()->create()));

        $this->assertFalse($tools['ok']);
        $this->assertSame('unreachable', $tools['error']);
        $this->assertSame([], $tools['pack']);
    }

    #[Test]
    public function the_archive_endpoints_need_the_account_password_first(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->stubArchiver();

        // A stolen bearer alone reaches nothing here: these endpoints read and
        // write any file on the target.
        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/files/archive-tools")
            ->assertStatus(403)
            ->assertJsonPath('error', 'locked');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/files/archive", ['paths' => ['/srv/data']])
            ->assertStatus(403)
            ->assertJsonPath('error', 'locked');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/files/extract", ['path' => '/srv/a.tar.gz'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'locked');
    }

    #[Test]
    public function the_archive_endpoints_are_owner_scoped(): void
    {
        $owner = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($owner);
        $this->stubArchiver();

        $stranger = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

        // 404, never 403: a stranger learns nothing about which servers exist.
        $this->actingAs($stranger)
            ->getJson("/api/v1/servers/{$server->id}/files/archive-tools")
            ->assertNotFound();

        $this->actingAs($stranger)
            ->postJson("/api/v1/servers/{$server->id}/files/extract", ['path' => '/srv/a.tar.gz'])
            ->assertNotFound();
    }

    #[Test]
    public function a_format_outside_the_offered_set_is_rejected_by_the_endpoint(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $probe = $this->stubArchiver();
        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/archive", [
                'paths' => ['/srv/data'], 'format' => 'zip',
            ])
            ->assertStatus(422);

        $this->assertSame([], $probe->scripts, 'a refused format never reaches the host');
    }

    #[Test]
    public function a_pack_streams_back_under_a_sensible_filename(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->stubArchiver();
        $token = $this->unlock($user, $server);

        $response = $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->post("/api/v1/servers/{$server->id}/files/archive", [
                'paths' => ['/srv/app/logs'], 'format' => 'tar.gz',
            ])
            ->assertOk();

        $this->assertSame(
            'attachment; filename="logs.tar.gz"',
            $response->headers->get('Content-Disposition'),
        );
        // Served like every other byte stream in this app.
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('ARCHIVE-BYTES', $response->streamedContent());
    }

    #[Test]
    public function an_unnameable_archive_is_refused_by_the_endpoint_and_still_audited(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->stubArchiver();
        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/extract", ['path' => '/srv/notes.txt'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_format');

        // Every attempt to unpack something onto a host leaves a trace, whether
        // it landed or not.
        $this->assertSame(1, AuditLog::query()->where('action', 'server.file_extracted')->count());
    }

    #[Test]
    public function a_successful_extraction_is_audited_with_the_destination_named(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->stubArchiver();
        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/extract", ['path' => '/srv/app/backup.tar.gz'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('dest', '/srv/app/backup');

        $log = AuditLog::query()->where('action', 'server.file_extracted')->firstOrFail();
        $this->assertSame('/srv/app/backup', $log->meta['dest']);
    }

    private function stubArchiver(): ArchiveRecordingProbe
    {
        $probe = new ArchiveRecordingProbe;
        $this->swap(RemoteArchiver::class, new RemoteArchiver($probe, new ArchiveStubSftp($probe)));

        return $probe;
    }

    private function unlock(User $user, Server $server): string
    {
        return (string) $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/files/unlock", ['password' => 'correct-horse-battery'])
            ->assertOk()
            ->json('token');
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
