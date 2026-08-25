<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Services\Backup\BackupDestinationFactory;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreDrill;
use App\Support\BlobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PDO;
use Tests\TestCase;

/**
 * The restore drill must prove a backup RESTORES (replay the dump, re-hash
 * sampled blobs against the live copies), stay read-only towards live data, and
 * fail loudly when the mirror no longer matches.
 */
class BackupDrillTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $remote;

    private string $remoteRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->remoteRoot = storage_path('framework/testing/drill-remote-'.Str::uuid());
        File::ensureDirectoryExists($this->remoteRoot);
        $this->remote = new Filesystem(new LocalFilesystemAdapter($this->remoteRoot));

        $remote = $this->remote;
        $this->instance(BackupDestinationFactory::class, new class($remote) extends BackupDestinationFactory
        {
            public function __construct(private Filesystem $fs) {}

            public function make(BackupDestination $destination): Filesystem
            {
                return $this->fs;
            }

            public function ensureRoot(Filesystem $fs, string $driver, array $config): void {}
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->remoteRoot);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function job(array $overrides = []): BackupJob
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        return BackupJob::create(array_merge([
            'name' => 'Docs', 'source' => 'invoices', 'sources' => ['invoices'],
            'mode' => 'full', 'keep_daily' => 7, 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 7, 'encrypt' => false, 'enabled' => true,
        ], $overrides));
    }

    private function prefix(BackupJob $job): string
    {
        return (Str::slug((string) $job->name) ?: 'backup').'-'.$job->id;
    }

    public function test_a_clean_backup_passes_the_drill(): void
    {
        BlobStore::disk()->put('invoices/a.pdf', 'ALPHA');
        BlobStore::disk()->put('invoices/b.pdf', 'BRAVO');

        $job = $this->job();
        $run = app(BackupManager::class)->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        $result = app(RestoreDrill::class)->run($job->fresh(), 10);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(2, $result['blobs']['checked']);
        $this->assertSame(2, $result['blobs']['matched']);
        $this->assertSame(0, $result['blobs']['mismatched']);
        $this->assertSame([], $result['blobs']['errors']);
        $this->assertFalse($result['database']['checked'], 'This job has no database source.');

        $this->artisan('backup:drill', ['--files' => 5])->assertExitCode(0);
    }

    public function test_a_database_dump_is_replayed_into_a_throwaway_database(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('The replay drill needs the sqlite driver.');
        }

        // A real on-disk SQLite database for DatabaseSource to dump. The live
        // :memory: connection used by the test itself stays untouched.
        $dbFile = $this->remoteRoot.'-live.sqlite';
        $pdo = new PDO('sqlite:'.$dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT)');
        $pdo->exec("INSERT INTO migrations (migration) VALUES ('a'), ('b'), ('c')");
        unset($pdo);
        config(['database.connections.sqlite.database' => $dbFile]);

        $job = $this->job(['name' => 'DB', 'source' => 'database', 'sources' => ['database']]);
        $run = app(BackupManager::class)->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        $result = app(RestoreDrill::class)->run($job->fresh(), 10);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertTrue($result['database']['checked']);
        $this->assertSame(1, $result['database']['tables']);
        $this->assertSame(3, $result['database']['rows'], 'The replayed database must contain the dumped rows.');

        // The drill only reads: the source database is byte-identical afterwards.
        $check = new PDO('sqlite:'.$dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $check->query('SELECT COUNT(*) FROM migrations');
        $this->assertNotFalse($stmt);
        $this->assertSame(3, (int) $stmt->fetchColumn());
        unset($check, $stmt);

        @unlink($dbFile);
    }

    public function test_a_tampered_or_missing_mirror_object_is_reported_and_exits_non_zero(): void
    {
        BlobStore::disk()->put('invoices/tampered.pdf', 'ORIGINAL');
        BlobStore::disk()->put('invoices/vanished.pdf', 'ALSO-ORIGINAL');

        $job = $this->job();
        $run = app(BackupManager::class)->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        $prefix = $this->prefix($job);
        // Bit rot on the destination: the object is there but no longer the file.
        $this->remote->write($prefix.'/mirror/invoices/tampered.pdf', 'CORRUPTED');
        // And one object silently gone while the ledger still claims it.
        $this->remote->delete($prefix.'/mirror/invoices/vanished.pdf');

        $result = app(RestoreDrill::class)->run($job->fresh(), 10);

        $this->assertFalse($result['ok']);
        $this->assertSame(1, $result['blobs']['mismatched']);
        $this->assertStringContainsString('tampered.pdf', implode(' ', $result['blobs']['mismatches']));
        $this->assertStringContainsString('vanished.pdf', implode(' ', $result['blobs']['errors']));

        $this->artisan('backup:drill', ['--files' => 10])->assertExitCode(1);

        // The drill never repairs or overwrites live data.
        $this->assertSame('ORIGINAL', BlobStore::disk()->get('invoices/tampered.pdf'));
        $this->assertSame('ALSO-ORIGINAL', BlobStore::disk()->get('invoices/vanished.pdf'));
    }

    public function test_the_drill_leaves_live_files_and_the_live_database_untouched(): void
    {
        BlobStore::disk()->put('invoices/live.pdf', 'LIVE-BYTES');

        $job = $this->job();
        $run = app(BackupManager::class)->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        $liveFiles = BlobStore::disk()->allFiles('invoices');
        $jobsBefore = BackupJob::count();
        $runsBefore = $job->runs()->count();

        $result = app(RestoreDrill::class)->run($job->fresh(), 10);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertSame($liveFiles, BlobStore::disk()->allFiles('invoices'));
        $this->assertSame('LIVE-BYTES', BlobStore::disk()->get('invoices/live.pdf'));
        $this->assertSame($jobsBefore, BackupJob::count());
        // Unlike backups:verify, the drill records nothing on the run either.
        $this->assertSame($runsBefore, $job->runs()->count());
        $this->assertNull($run->fresh()->verified_at);
    }

    public function test_an_encrypted_mirror_is_decrypted_before_comparing(): void
    {
        BlobStore::disk()->put('invoices/secret.pdf', 'TOP-SECRET');

        $job = $this->job(['encrypt' => true, 'passphrase' => 'correct horse battery staple']);
        $run = app(BackupManager::class)->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        // The mirrored bytes are ciphertext, so a naive byte comparison would
        // "fail"; the drill must restore them first.
        $prefix = $this->prefix($job);
        $this->assertNotSame('TOP-SECRET', $this->remote->read($prefix.'/mirror/invoices/secret.pdf'));

        $result = app(RestoreDrill::class)->run($job->fresh(), 10);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, $result['blobs']['matched']);
    }
}
