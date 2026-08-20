<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StorageMount;
use App\Models\User;
use App\Services\Backup\BackupDestinationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tests\TestCase;

/**
 * Exercises the external-mount browse/transfer flow against a LOCAL Flysystem
 * (the S3/SFTP factory is faked to a temp dir) plus owner-scope, read-only,
 * credential hiding and path-traversal guards.
 */
class StorageMountTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/mount-'.bin2hex(random_bytes(6));
        @mkdir($this->root, 0700, true);

        // Fake the remote-filesystem factory with a local one rooted at a temp dir.
        $root = $this->root;
        $this->app->instance(BackupDestinationFactory::class, new class($root) extends BackupDestinationFactory
        {
            public function __construct(private string $root) {}

            // Signature must track the real factory: it grew an $interactive
            // flag (shorter timeouts for the live mount browser) after this
            // stub was written, which made the whole class fatal at load time.
            public function makeFromParts(string $driver, array $c, bool $interactive = false): Filesystem
            {
                return new Filesystem(new LocalFilesystemAdapter($this->root));
            }

            public function test(string $driver, array $config): void
            {
                $this->makeFromParts($driver, $config)->write('.probe', 'ok');
                $this->makeFromParts($driver, $config)->delete('.probe');
            }
        });
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function makeMount(User $user, bool $readOnly = false): StorageMount
    {
        $m = new StorageMount;
        $m->forceFill(['user_id' => $user->id, 'name' => 'Box', 'type' => 'sftp', 'read_only' => $readOnly, 'config' => ['host' => 'h', 'username' => 'u', 'password' => 'p']])->save();

        return $m;
    }

    public function test_create_lists_never_leaks_credentials(): void
    {
        $this->actingAs(User::factory()->create());
        $res = $this->postJson(route('mounts.store'), [
            'name' => 'My S3', 'type' => 's3', 'bucket' => 'b', 'key' => 'k', 'secret' => 's', 'region' => 'us-east-1',
        ])->assertCreated();
        $res->assertJsonPath('mount.name', 'My S3');
        $res->assertJsonMissingPath('mount.config');

        $this->getJson(route('mounts.index'))->assertOk()->assertJsonMissingPath('mounts.0.config');
    }

    public function test_upload_list_download_delete_roundtrip(): void
    {
        $user = User::factory()->create();
        $mount = $this->makeMount($user);
        $this->actingAs($user);

        $this->post(route('mounts.upload', $mount), ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hi'), 'path' => 'sub'])->assertCreated();

        $list = $this->getJson(route('mounts.list', $mount).'?path=sub')->assertOk()->json();
        $this->assertSame('a.txt', $list['files'][0]['name']);

        $dl = $this->get(route('mounts.download', $mount).'?path=sub/a.txt')->assertOk();
        $this->assertSame('hi', $dl->streamedContent());

        $this->postJson(route('mounts.delete-path', $mount), ['path' => 'sub/a.txt'])->assertOk();
        $this->assertCount(0, $this->getJson(route('mounts.list', $mount).'?path=sub')->json('files'));
    }

    public function test_read_only_mount_blocks_writes(): void
    {
        $user = User::factory()->create();
        $mount = $this->makeMount($user, readOnly: true);
        $this->actingAs($user);

        $this->post(route('mounts.upload', $mount), ['file' => UploadedFile::fake()->createWithContent('x.txt', 'x')])->assertForbidden();
        $this->postJson(route('mounts.mkdir', $mount), ['name' => 'nope'])->assertForbidden();
        $this->postJson(route('mounts.delete-path', $mount), ['path' => 'x.txt'])->assertForbidden();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $user = User::factory()->create();
        $mount = $this->makeMount($user);
        $this->actingAs($user);

        $this->getJson(route('mounts.list', $mount).'?path='.urlencode('../../etc'))->assertStatus(422);
        $this->postJson(route('mounts.mkdir', $mount), ['name' => '..'])->assertStatus(422);
    }

    public function test_mounts_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $mount = $this->makeMount($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson(route('mounts.list', $mount))->assertNotFound();
        $this->actingAs($stranger)->deleteJson(route('mounts.destroy', $mount))->assertNotFound();
    }

    public function test_a_webdav_mount_is_accepted_and_its_password_is_never_returned(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('mounts.store'), [
            'name' => 'Cloud', 'type' => 'webdav',
            'base_uri' => 'https://dav.example.com/remote/', 'username' => 'me', 'password' => 'secret',
        ])->assertCreated()->assertJsonPath('mount.type', 'webdav')->assertJsonMissingPath('mount.config');

        $this->assertSame('https://dav.example.com/remote/', StorageMount::query()->firstOrFail()->config['base_uri']);
    }

    public function test_a_nextcloud_mount_derives_the_per_user_dav_path(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('mounts.store'), [
            'name' => 'NC', 'type' => 'nextcloud',
            'server' => 'https://cloud.example.com/', 'username' => 'a b', 'password' => 'app-pw',
        ])->assertCreated();

        // The user gives the server address and their login; the remote.php path
        // is assembled, and the login has to survive as a URL segment.
        $this->assertSame(
            'https://cloud.example.com/remote.php/dav/files/a%20b/',
            StorageMount::query()->firstOrFail()->config['base_uri'],
        );
    }

    public function test_a_b2_mount_derives_its_s3_endpoint_from_the_region(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('mounts.store'), [
            'name' => 'B2', 'type' => 'b2', 'bucket' => 'bk', 'region' => 'eu-central-003',
            'key' => 'k', 'secret' => 's',
        ])->assertCreated();

        $config = StorageMount::query()->firstOrFail()->config;
        $this->assertSame('https://s3.eu-central-003.backblazeb2.com', $config['endpoint']);
        $this->assertFalse($config['use_path_style']);
    }

    public function test_an_unknown_mount_type_is_rejected(): void
    {
        // Asserted on the API twin: a validation failure on the web route is a
        // 302 redirect, so a status assertion there would prove nothing.
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];

        $this->postJson(route('api.mounts.store'), ['name' => 'X', 'type' => 'dropbox'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertSame(0, StorageMount::query()->withoutGlobalScopes()->count());
    }
}
