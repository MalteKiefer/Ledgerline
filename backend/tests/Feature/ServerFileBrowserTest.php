<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\FilePermissions;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use App\Services\Servers\SftpBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A stand-in for the SFTP client that records what would have been sent.
 *
 * The commands are the contract worth asserting on: quoting, path handling and
 * the staging-then-rename dance all show up there, and none of them need a real
 * connection to be verified.
 */
class RecordingSftpBrowser extends SftpBrowser
{
    /** @var list<string> */
    public array $sent = [];

    public string $output = '';

    public bool $succeed = true;

    /** Written into the local file a `get` would have produced. */
    public string $payload = '';

    public function __construct(ServerProbe $probe)
    {
        parent::__construct($probe);
    }

    protected function batch(Server $server, array $commands, int $timeout): ?array
    {
        foreach ($commands as $c) {
            $this->sent[] = $c;
            // A `get` writes the remote file locally; the double writes what the
            // test says the host holds, so read() sees a real file.
            if (str_starts_with($c, 'get ') && preg_match('/ "([^"]+)"$/', $c, $m) === 1) {
                file_put_contents($m[1], $this->payload);
            }
        }

        return ['ok' => $this->succeed, 'out' => $this->output, 'err' => '', 'exit' => $this->succeed ? 0 : 1];
    }
}

class ServerFileBrowserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nothing_opens_without_the_account_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->recorder();

        // A stolen bearer alone does not open a filesystem.
        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$server->id}/files?path=/etc")
            ->assertStatus(403)
            ->assertJsonPath('error', 'locked');

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/files/unlock", ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'bad_password');
    }

    #[Test]
    public function unlocking_is_audited_and_grants_access(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();
        $sftp->output = "sftp> ls -la \"/etc\"\n"
            ."drwxr-xr-x    ? root     root         4096 Aug 22 06:50 /etc/ssh\n"
            ."-rw-r--r--    ? root     root          812 Jan  5  2023 /etc/hosts\n";

        $token = $this->unlock($user, $server);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.files_unlocked')->count());

        $body = $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files?path=/etc")
            ->assertOk()
            ->json();

        $this->assertSame(['ssh', 'hosts'], array_column($body['entries'], 'name'));
        $this->assertSame('dir', $body['entries'][0]['type']);
        $this->assertSame(812, $body['entries'][1]['size']);
        // Left as the host printed it: sftp gives no year for a recent file, so
        // an exact timestamp would be invented.
        $this->assertSame('Jan  5  2023', $body['entries'][1]['modified']);
    }

    #[Test]
    public function a_grant_is_bound_to_one_account_and_one_server(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $other = $this->server($user);
        $this->recorder();

        $token = $this->unlock($user, $server);

        // Same token, different server: the token alone is not a capability.
        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$other->id}/files?path=/etc")
            ->assertStatus(403)
            ->assertJsonPath('error', 'locked');
    }

    #[Test]
    public function names_with_spaces_and_quotes_survive_the_listing(): void
    {
        $sftp = $this->recorder();
        $sftp->output = "sftp> ls -la \"/tmp\"\n"
            ."-rw-r--r--    ? root     root            0 Aug 22 06:52 /tmp/a b.txt\n"
            ."-rw-r--r--    ? root     root            0 Aug 22 06:52 /tmp/quote\"x.txt\n";

        $rows = $sftp->list($this->server(User::factory()->create()), '/tmp');

        // sftp prints the full path, which is what makes this parseable at all:
        // the name is everything after the directory prefix, spaces included.
        $this->assertSame(['a b.txt', 'quote"x.txt'], array_column($rows['entries'], 'name'));
    }

    #[Test]
    public function a_path_that_cannot_be_expressed_is_refused(): void
    {
        $sftp = $this->recorder();
        $server = $this->server(User::factory()->create());

        // sftp's batch input is line-based, so a newline in a path could never
        // be sent — refusing it is honest, truncating it would not be.
        $this->assertSame('invalid_path', $sftp->list($server, "/tmp/a\nb")['error']);
        $this->assertSame('invalid_path', $sftp->list($server, 'relative/path')['error']);
        $this->assertSame([], $sftp->sent);
    }

    #[Test]
    public function traversal_is_resolved_before_the_path_is_sent(): void
    {
        $sftp = $this->recorder();

        $sftp->list($this->server(User::factory()->create()), '/etc/ssh/../../var//log/');

        // Collapsed here, so no check downstream can be walked past.
        $this->assertSame(['ls -la "/var/log"'], $sftp->sent);
    }

    #[Test]
    public function a_quote_in_a_path_is_escaped_on_the_way_out(): void
    {
        $sftp = $this->recorder();

        $sftp->list($this->server(User::factory()->create()), '/tmp/quote"x');

        // Verified against the real client: inside double quotes, \" escapes.
        $this->assertSame(['ls -la "/tmp/quote\\"x"'], $sftp->sent);
    }

    #[Test]
    public function a_binary_is_refused_rather_than_handed_to_an_editor(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();
        // Opening a binary in an editor and saving it back destroys the file.
        $sftp->payload = "PNG\x00\x01\x02binary\x00payload";

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files/read?path=/tmp/x.png")
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('binary', true);
    }

    #[Test]
    public function text_reads_back_intact(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();
        $sftp->payload = "line one\nline two\n";

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files/read?path=/etc/motd")
            ->assertOk()
            ->assertJsonPath('ok', true)
            // Base64 on the wire: the framework trims request strings, which
            // would otherwise eat the trailing newline of every file saved.
            ->assertJsonPath('content', base64_encode("line one\nline two\n"));
    }

    #[Test]
    public function a_write_lands_via_a_neighbouring_name_and_is_audited(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/write", ['path' => '/etc/motd', 'content' => base64_encode("hello\n")])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Uploaded beside the target and renamed into place: an upload cut
        // short is otherwise a half-written configuration file.
        $sent = implode("\n", $sftp->sent);
        $this->assertMatchesRegularExpression('/put ".+" "\/etc\/motd\.ll-upload-[0-9a-f]{8}"/', $sent);
        $this->assertMatchesRegularExpression('/rename "\/etc\/motd\.ll-upload-[0-9a-f]{8}" "\/etc\/motd"/', $sent);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.file_written')->count());
    }

    #[Test]
    public function a_failed_upload_does_not_leave_the_fragment_behind(): void
    {
        $sftp = $this->recorder();
        $sftp->succeed = false;
        $sftp->output = 'Permission denied';

        $result = $sftp->write($this->server(User::factory()->create()), '/etc/motd', 'x');

        $this->assertFalse($result['ok']);
        $this->assertSame('permission_denied', $result['error']);
        // The staging name is cleaned up rather than left as litter next to a
        // file the operator will look at again.
        $this->assertStringContainsString('rm "/etc/motd.ll-upload-', end($sftp->sent));
    }

    #[Test]
    public function a_trailing_newline_survives_a_save(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();
        $sftp->payload = 'config line
';

        $token = $this->unlock($user, $server);

        // The exact byte the framework's string trimming would have eaten, and
        // the one whose absence breaks a config file the next tool reads.
        $read = $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files/read?path=/etc/x.conf")
            ->assertOk()
            ->json('content');

        $this->assertSame('config line
', base64_decode((string) $read, true));
    }

    #[Test]
    public function a_mode_that_is_not_a_mode_never_reaches_the_host(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/mutate", [
                'action' => 'chmod', 'path' => '/etc/motd', 'mode' => '777; rm -rf /',
            ])
            ->assertStatus(422);

        $this->assertSame([], $sftp->sent);
    }

    #[Test]
    public function mutations_are_audited_with_the_path_named(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $sftp = $this->recorder();

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/mutate", [
                'action' => 'rename', 'path' => '/tmp/a', 'target' => '/tmp/b',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(['rename "/tmp/a" "/tmp/b"'], $sftp->sent);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.file_mutated')->count());
    }

    #[Test]
    public function permissions_report_missing_acl_tools_as_unreadable(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->recorder();
        $this->swapPermissions('##LL:stat
644 root root 0 0 regular file
##LL:acl
__absent__
##LL:users
root
www-data
##LL:groups
root
##LL:end
');

        $token = $this->unlock($user, $server);

        $body = $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files/permissions?path=/etc/motd")
            ->assertOk()
            ->json();

        $this->assertSame('0644', $body['mode']);
        $this->assertSame('root', $body['owner']);
        // A host without the acl package is not a host without access control:
        // saying "no ACLs" here would be the same lie as calling an unreadable
        // firewall an empty one.
        $this->assertFalse($body['acl_supported']);
        $this->assertSame([], $body['acl']);
        $this->assertSame(['root', 'www-data'], $body['users']);
    }

    #[Test]
    public function acl_entries_are_reported_when_the_tools_exist(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->recorder();
        $this->swapPermissions('##LL:stat
2775 root staff 0 50 directory
##LL:acl
user::rwx
user:alice:r-x
group::r-x
mask::r-x
other::r-x
##LL:users
root
##LL:groups
staff
##LL:end
');

        $token = $this->unlock($user, $server);

        $body = $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->getJson("/api/v1/servers/{$server->id}/files/permissions?path=/srv")
            ->assertOk()
            ->json();

        $this->assertSame('2775', $body['mode'], 'the setgid bit belongs in the mode, not silently dropped');
        $this->assertTrue($body['acl_supported']);
        $this->assertContains('user:alice:r-x', $body['acl']);
    }

    #[Test]
    public function an_account_name_that_is_not_one_never_reaches_the_host(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->recorder();
        $perms = $this->swapPermissions('');

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/permissions", [
                'path' => '/etc/motd', 'owner' => 'root; rm -rf /',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_owner');

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/permissions", [
                'path' => '/etc/motd', 'acl' => ['u:alice:rwx; reboot'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_acl');

        $this->assertNull($perms->script);
    }

    #[Test]
    public function a_permission_change_is_audited(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
        $server = $this->server($user);
        $this->recorder();
        $perms = $this->swapPermissions('##LL:rc=0
');

        $token = $this->unlock($user, $server);

        $this->actingAs($user)
            ->withHeader('X-File-Grant', $token)
            ->postJson("/api/v1/servers/{$server->id}/files/permissions", [
                'path' => '/etc/motd', 'mode' => '4755', 'owner' => 'root', 'group' => 'staff',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $script = (string) $perms->script;
        $this->assertStringContainsString("chmod 4755 -- '/etc/motd'", $script);
        $this->assertStringContainsString("chown 'root:staff' -- '/etc/motd'", $script);
        $this->assertSame(1, AuditLog::query()->where('action', 'server.file_permissions')->count());
    }

    private function unlock(User $user, Server $server): string
    {
        return (string) $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/files/unlock", ['password' => 'correct-horse-battery'])
            ->assertOk()
            ->json('token');
    }

    private function swapPermissions(string $output): ServerProbe
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

        $this->swap(FilePermissions::class, new FilePermissions($probe));

        return $probe;
    }

    private function recorder(): RecordingSftpBrowser
    {
        $probe = new ServerProbe;
        $sftp = new RecordingSftpBrowser($probe);
        $this->swap(SftpBrowser::class, $sftp);

        return $sftp;
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
