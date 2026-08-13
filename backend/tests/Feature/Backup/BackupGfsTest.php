<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Services\Backup\BackupDestinationFactory;
use App\Services\Backup\BackupManager;
use App\Support\BlobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tests\TestCase;

/**
 * Backup model: blob sources are MIRRORED to <prefix>/mirror/<key> (delta-only, no
 * archive, resumable), the database is dumped point-in-time into <prefix>/db/<ts>
 * (GFS-rotated), and a blob source restores in-place from the mirror. Uses a local
 * in-memory destination so no real remote is touched.
 */
class BackupGfsTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $remote;

    private string $remoteRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->remoteRoot = storage_path('framework/testing/backup-remote-'.Str::uuid());
        File::ensureDirectoryExists($this->remoteRoot);
        $this->remote = new Filesystem(new LocalFilesystemAdapter($this->remoteRoot));

        // A destination factory that always yields the shared in-memory filesystem.
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
        return (Str::slug($job->name) ?: 'backup').'-'.$job->id;
    }

    public function test_blob_source_is_mirrored_and_restores_in_place(): void
    {
        BlobStore::disk()->put('invoices/keep.pdf', 'ORIGINAL');

        $job = $this->job();
        $manager = app(BackupManager::class);
        $run = $manager->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');
        // A successful run stamps the job (last_run_at is a non-fillable operational
        // field written via forceFill — a plain update() silently dropped it).
        $this->assertNotNull($job->fresh()->last_run_at);
        $this->assertSame('success', $job->fresh()->last_status);

        // The object landed in the living mirror (key keeps its source prefix), plus a ledger.
        $prefix = $this->prefix($job);
        $this->assertTrue($this->remote->fileExists($prefix.'/mirror/invoices/keep.pdf'));
        $this->assertSame('ORIGINAL', $this->remote->read($prefix.'/mirror/invoices/keep.pdf'));
        $this->assertTrue($this->remote->fileExists($prefix.'/mirror/.ledger-invoices.json'));

        // Wipe the live copy, then restore it from the mirror.
        BlobStore::disk()->delete('invoices/keep.pdf');
        $this->assertFalse(BlobStore::disk()->exists('invoices/keep.pdf'));

        $written = $manager->restoreBlobs($job->fresh(), (string) $run->filename, 'invoices');
        $this->assertGreaterThanOrEqual(1, $written);
        $this->assertSame('ORIGINAL', BlobStore::disk()->get('invoices/keep.pdf'));
    }

    public function test_mirror_uploads_only_the_delta_on_subsequent_runs(): void
    {
        BlobStore::disk()->put('invoices/a.pdf', 'AAA');

        $job = $this->job();
        $manager = app(BackupManager::class);

        $run1 = $manager->run($job);
        $this->assertSame('success', $run1->status, $run1->log ?? '');
        $this->assertSame(3, $run1->bytes); // uploaded a.pdf (3 bytes)

        // Nothing changed → second run uploads zero bytes (delta-only, no re-archiving).
        $run2 = $manager->run($job->fresh());
        $this->assertSame('success', $run2->status, $run2->log ?? '');
        $this->assertSame(0, $run2->bytes);

        // A new file → only that file is uploaded next run.
        BlobStore::disk()->put('invoices/b.pdf', 'BBBBB');
        $run3 = $manager->run($job->fresh());
        $this->assertSame(5, $run3->bytes);

        // A changed file (different size) → re-uploaded.
        BlobStore::disk()->put('invoices/a.pdf', 'AAAA');
        $run4 = $manager->run($job->fresh());
        $this->assertSame(4, $run4->bytes);
    }

    public function test_database_dumps_land_in_gfs_rotated_batches(): void
    {
        // The test DB is in-memory sqlite (not dumpable); point the sqlite driver at a
        // real temp file so DatabaseSource has bytes to dump. The live :memory: PDO
        // connection (already migrated by RefreshDatabase) is untouched by this config.
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('DB dump batch test requires the sqlite driver.');
        }
        $dbFile = $this->remoteRoot.'-db.sqlite';
        file_put_contents($dbFile, "SQLite format 3\0");
        config(['database.connections.sqlite.database' => $dbFile]);

        $job = $this->job(['name' => 'DB', 'source' => 'database', 'sources' => ['database'], 'keep_daily' => 1]);
        $manager = app(BackupManager::class);
        $prefix = $this->prefix($job);

        $run1 = $manager->run($job);
        $this->assertSame('success', $run1->status, $run1->log ?? '');
        // filename anchors the DB batch; its dump is a real, resolvable archive.
        $this->assertStringStartsWith($prefix.'/db/', (string) $run1->filename);
        $this->assertNotNull($manager->archiveIn($this->remote, (string) $run1->filename, 'database'));

        // A second run makes a new batch; keep_daily=1 prunes the older one.
        $run2 = $manager->run($job->fresh());
        $this->assertSame('success', $run2->status, $run2->log ?? '');
        $batches = array_values(array_filter(
            iterator_to_array($this->remote->listContents($prefix.'/db', false)),
            fn ($i): bool => method_exists($i, 'isDir') ? $i->isDir() : ! $i->isFile()
        ));
        $this->assertCount(1, $batches, 'GFS keep_daily=1 should keep exactly one DB batch.');
    }

    public function test_encrypted_mirror_round_trips(): void
    {
        BlobStore::disk()->put('invoices/secret.pdf', 'TOP-SECRET');

        $job = $this->job(['encrypt' => true, 'passphrase' => 'correct horse battery staple']);
        $manager = app(BackupManager::class);
        $run = $manager->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        // The mirrored bytes are ciphertext (not the plaintext), and the ledger records enc.
        $prefix = $this->prefix($job);
        $this->assertNotSame('TOP-SECRET', $this->remote->read($prefix.'/mirror/invoices/secret.pdf'));

        // Restore decrypts back to the original plaintext.
        BlobStore::disk()->delete('invoices/secret.pdf');
        $manager->restoreBlobs($job->fresh(), (string) $run->filename, 'invoices');
        $this->assertSame('TOP-SECRET', BlobStore::disk()->get('invoices/secret.pdf'));
    }

    public function test_effective_sources_and_retention_tiers_fall_back_to_legacy(): void
    {
        $job = new BackupJob(['source' => 'files', 'retention' => 9]);
        $this->assertSame(['files'], $job->effectiveSources());
        $this->assertSame(['daily' => 9, 'weekly' => 0, 'monthly' => 0], $job->retentionTiers());

        $job2 = new BackupJob(['sources' => ['invoices', 'files', 'invoices'], 'keep_daily' => 3, 'keep_weekly' => 2, 'keep_monthly' => 1]);
        $this->assertSame(['invoices', 'files'], $job2->effectiveSources());
        $this->assertSame(['daily' => 3, 'weekly' => 2, 'monthly' => 1], $job2->retentionTiers());
    }
}
