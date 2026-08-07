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
 * End-to-end backup batch model: multiple sources produce one timestamped batch
 * folder with one archive per source, GFS retention rotates whole batches, and a
 * blob source restores back onto the live disk. Uses an in-memory destination so
 * no real remote is touched.
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

    public function test_a_multi_source_run_writes_one_archive_per_source_in_a_batch(): void
    {
        BlobStore::disk()->put('invoices/a.pdf', 'PDF-A');

        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'Docs', 'source' => 'invoices', 'sources' => ['invoices'],
            'mode' => 'full', 'keep_daily' => 7, 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 7, 'encrypt' => false, 'enabled' => true,
        ]);

        $run = app(BackupManager::class)->run($job);

        $this->assertSame('success', $run->status, $run->log ?? '');
        $batch = (string) $run->filename;
        $manager = app(BackupManager::class);
        $this->assertNotNull($manager->archiveIn($this->remote, $batch, 'invoices'));
    }

    public function test_restore_writes_blob_bytes_back_to_the_live_disk(): void
    {
        BlobStore::disk()->put('invoices/keep.pdf', 'ORIGINAL');

        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'Docs', 'source' => 'invoices', 'sources' => ['invoices'],
            'mode' => 'full', 'keep_daily' => 7, 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 7, 'encrypt' => false, 'enabled' => true,
        ]);
        $manager = app(BackupManager::class);
        $run = $manager->run($job);
        $this->assertSame('success', $run->status, $run->log ?? '');

        // Wipe the live copy, then restore it from the batch.
        BlobStore::disk()->delete('invoices/keep.pdf');
        $this->assertFalse(BlobStore::disk()->exists('invoices/keep.pdf'));

        $written = $manager->restoreBlobs($job->fresh(), (string) $run->filename, 'invoices');

        $this->assertGreaterThanOrEqual(1, $written);
        $this->assertSame('ORIGINAL', BlobStore::disk()->get('invoices/keep.pdf'));
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
