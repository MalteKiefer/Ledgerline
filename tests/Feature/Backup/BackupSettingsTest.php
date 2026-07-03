<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Jobs\RunBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Services\Backup\BackupDestinationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackupSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** Stub the factory so destination saves don't hit a real remote. */
    private function fakeReachableDestinations(): void
    {
        $this->mock(BackupDestinationFactory::class, function ($mock): void {
            $mock->shouldReceive('test')->andReturnNull();
        });
    }

    public function test_the_page_loads(): void
    {
        $this->signIn();
        $this->get(route('settings.backup.index'))->assertOk();
    }

    public function test_a_destination_is_created_with_an_encrypted_config(): void
    {
        $this->signIn();
        $this->fakeReachableDestinations();

        $this->post(route('settings.backup.destinations.store'), [
            'name' => 'Wasabi',
            'driver' => 's3',
            'bucket' => 'my-bucket',
            'region' => 'eu-central-1',
            'key' => 'AKIA',
            'secret' => 'topsecret',
            'endpoint' => 'https://s3.example.test',
        ])->assertRedirect();

        $dest = BackupDestination::firstOrFail();
        $this->assertSame('my-bucket', $dest->config['bucket']);
        $this->assertSame('topsecret', $dest->config['secret']);
        // Not stored as plaintext.
        $this->assertStringNotContainsString('topsecret', (string) \DB::table('backup_destinations')->value('config'));
    }

    public function test_a_job_is_created(): void
    {
        $this->signIn();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $this->post(route('settings.backup.jobs.store'), [
            'name' => 'DB every 3h',
            'source' => 'database',
            'backup_destination_id' => $dest->id,
            'cron' => '0 */3 * * *',
            'retention' => 5,
            'notify' => 'none',
            'enabled' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('backup_jobs', ['name' => 'DB every 3h', 'source' => 'database', 'retention' => 5]);
    }

    public function test_an_invalid_cron_is_rejected(): void
    {
        $this->signIn();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $this->post(route('settings.backup.jobs.store'), [
            'name' => 'Bad', 'source' => 'files', 'backup_destination_id' => $dest->id,
            'cron' => 'not a cron', 'retention' => 3, 'notify' => 'none',
        ])->assertSessionHasErrors('cron');
    }

    public function test_encryption_requires_a_passphrase_on_create(): void
    {
        $this->signIn();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $this->post(route('settings.backup.jobs.store'), [
            'name' => 'Enc', 'source' => 'files', 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 3, 'notify' => 'none',
            'encrypt' => '1', 'passphrase' => '',
        ])->assertSessionHasErrors('passphrase');
    }

    public function test_run_now_queues_a_backup(): void
    {
        Queue::fake();
        $this->signIn();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'J', 'source' => 'database', 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 3, 'notify' => 'none', 'enabled' => true,
        ]);

        $this->post(route('settings.backup.jobs.run', $job))->assertRedirect();

        Queue::assertPushed(RunBackupJob::class, fn ($j) => $j->backupJobId === $job->id);
    }

    public function test_an_unreachable_destination_is_not_saved(): void
    {
        $this->signIn();
        $this->mock(BackupDestinationFactory::class, function ($mock): void {
            $mock->shouldReceive('test')->andThrow(new \RuntimeException('connection refused'));
        });

        $this->post(route('settings.backup.destinations.store'), [
            'name' => 'Broken', 'driver' => 'sftp', 'host' => 'nope.test', 'port' => 22,
            'username' => 'u', 'password' => 'p', 'path' => '/',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('backup_destinations', 0);
    }

    public function test_the_test_endpoint_reports_reachability(): void
    {
        $this->signIn();
        $this->mock(BackupDestinationFactory::class, function ($mock): void {
            $mock->shouldReceive('test')->once()->andReturnNull();
        });

        $this->postJson(route('settings.backup.destinations.test'), [
            'name' => 'X', 'driver' => 's3', 'bucket' => 'b', 'region' => 'r', 'key' => 'k', 'secret' => 's',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_the_test_endpoint_reports_a_failure_as_json(): void
    {
        $this->signIn();
        $this->mock(BackupDestinationFactory::class, function ($mock): void {
            $mock->shouldReceive('test')->andThrow(new \RuntimeException('connection refused'));
        });

        $this->postJson(route('settings.backup.destinations.test'), [
            'name' => 'X', 'driver' => 'sftp', 'host' => 'nope.test', 'port' => 22, 'username' => 'u', 'password' => 'p',
        ])->assertOk()->assertJson(['ok' => false]);
    }
}
