<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Services\Backup\ArchiveCipher;
use App\Services\Backup\BackupDestinationFactory;
use App\Services\Backup\BackupVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tests\TestCase;

class BackupVerifierTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/llverify_'.uniqid();
        File::ensureDirectoryExists($this->dir);

        // Point the destination factory at a local temp folder so the verifier
        // reads a real archive without needing S3/SFTP.
        $fs = new Filesystem(new LocalFilesystemAdapter($this->dir));
        $fake = \Mockery::mock(BackupDestinationFactory::class);
        $fake->shouldReceive('make')->andReturn($fs);
        $this->app->instance(BackupDestinationFactory::class, $fake);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function makeRun(string $filename): BackupRun
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'J', 'source' => 'database', 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 5, 'notify_channels' => [], 'enabled' => true,
        ]);

        return BackupRun::create([
            'backup_job_id' => $job->id, 'status' => 'success',
            'started_at' => now(), 'finished_at' => now(), 'bytes' => 1, 'filename' => $filename,
        ]);
    }

    private function gzDump(string $sql): string
    {
        $path = $this->dir.'/database.sql.gz';
        $gz = gzopen($path, 'wb9');
        gzwrite($gz, $sql);
        gzclose($gz);

        return $path;
    }

    public function test_it_verifies_a_plain_sql_dump(): void
    {
        $this->gzDump("-- dump\nCREATE TABLE users (id int);\nCREATE TABLE notes (id int);\n");
        $run = $this->makeRun('database.sql.gz');

        $result = app(BackupVerifier::class)->verify($run, null);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('2 table', $result['message']);
        $this->assertSame('ok', $run->fresh()->verify_status);
    }

    public function test_it_verifies_an_encrypted_dump_with_the_passphrase(): void
    {
        $plain = $this->gzDump("CREATE TABLE users (id int);\n");
        $enc = $this->dir.'/database.sql.gz.enc';
        (new ArchiveCipher)->encryptFile($plain, $enc, 'secret');
        @unlink($plain);
        $run = $this->makeRun('database.sql.gz.enc');

        $ok = app(BackupVerifier::class)->verify($run, 'secret');
        $this->assertTrue($ok['ok']);

        $bad = app(BackupVerifier::class)->verify($run, 'wrong');
        $this->assertFalse($bad['ok']);
        $this->assertSame('failed', $run->fresh()->verify_status);
    }

    public function test_a_missing_passphrase_fails_for_an_encrypted_archive(): void
    {
        $plain = $this->gzDump("CREATE TABLE users (id int);\n");
        (new ArchiveCipher)->encryptFile($plain, $this->dir.'/database.sql.gz.enc', 'secret');
        @unlink($plain);
        $run = $this->makeRun('database.sql.gz.enc');

        $result = app(BackupVerifier::class)->verify($run, null);

        $this->assertFalse($result['ok']);
    }
}
