<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\RunBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupDestinationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Admin backup management over /api/v1/backup/* (Sanctum device token +
 * manage-global-settings).
 *
 * Critical invariants verified here:
 *   1. GET /destinations never leaks destination.config (remote credentials).
 *   2. GET /jobs never leaks job.passphrase (vault-key protection).
 *   3. source=database + encrypt=false → allowed (operator opt-out; UI warns).
 *   4. Non-admin tokens → 403 on all endpoints.
 */
class BackupApiTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /*  Token helpers */
    /* ------------------------------------------------------------------ */

    private function adminToken(): string
    {
        return User::factory()->admin()->create()
            ->createToken('test-device', ['device'])->plainTextToken;
    }

    private function userToken(): string
    {
        return User::factory()->create()
            ->createToken('test-device', ['device'])->plainTextToken;
    }

    /** Stub the factory so destination saves / tests don't hit a real remote. */
    private function fakeReachable(): void
    {
        $this->mock(BackupDestinationFactory::class, function (MockInterface $mock): void {
            /** @var Expectation $e */
            $e = $mock->shouldReceive('test');
            $e->andReturnNull();
        });
    }

    /**
     * Common valid destination payload.
     *
     * @return array<string, mixed>
     */
    private function destPayload(): array
    {
        return [
            'name' => 'Wasabi-Test',
            'driver' => 's3',
            'bucket' => 'my-bucket',
            'region' => 'eu-central-1',
            'key' => 'AKIAIOSFODNN7EXAMPLE',
            'secret' => 'super-secret-s3-key',
            'endpoint' => 'https://s3.example.test',
        ];
    }

    /**
     * Common valid job payload.
     *
     * @return array<string, mixed>
     */
    private function jobPayload(int $destId): array
    {
        return [
            'name' => 'Nightly DB',
            'source' => 'database',
            'backup_destination_id' => $destId,
            'cron' => '0 3 * * *',
            'retention' => 7,
            'encrypt' => true,
            'passphrase' => 'a-very-long-passphrase-for-testing',
            'enabled' => true,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Non-admin guard */
    /* ------------------------------------------------------------------ */

    public function test_non_admin_is_forbidden_on_all_backup_endpoints(): void
    {
        $token = $this->userToken();
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/backup/destinations', $headers)->assertForbidden();
        $this->postJson('/api/v1/backup/destinations', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/backup/jobs', $headers)->assertForbidden();
        $this->postJson('/api/v1/backup/jobs', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/backup/runs', $headers)->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Destinations — credential hygiene */
    /* ------------------------------------------------------------------ */

    public function test_destinations_list_never_includes_config(): void
    {
        BackupDestination::create([
            'name' => 'Prod S3',
            'driver' => 's3',
            'config' => ['key' => 'AKIAIOSFODNN7EXAMPLE', 'secret' => 'wJalrXUtnFEMI/K7MDENGbPxRfiCYEXAMPLEKEY', 'bucket' => 'vault'],
        ]);

        $response = $this->getJson('/api/v1/backup/destinations', [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk();

        $response->assertJsonStructure(['destinations' => [['id', 'name', 'driver']]]);
        $response->assertJsonMissing(['config']);
        $response->assertJsonMissing(['secret']);
        $response->assertJsonMissing(['key']);
        $response->assertJsonMissing(['AKIAIOSFODNN7EXAMPLE']);
    }

    public function test_admin_can_create_a_destination(): void
    {
        $this->fakeReachable();

        $response = $this->postJson('/api/v1/backup/destinations', $this->destPayload(), [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertCreated();

        $response->assertJsonPath('destination.name', 'Wasabi-Test');
        $response->assertJsonPath('destination.driver', 's3');
        $response->assertJsonMissing(['config']);
        $response->assertJsonMissing(['secret']);

        // Verify it was actually stored with the encrypted config.
        $dest = BackupDestination::firstOrFail();
        $config = $dest->config;
        $this->assertIsArray($config);
        $this->assertSame('my-bucket', $config['bucket']);
        // Encrypted at rest (not visible as plaintext in the DB column).
        $raw = \DB::table('backup_destinations')->value('config');
        $this->assertStringNotContainsString(
            'super-secret-s3-key',
            is_string($raw) ? $raw : '',
        );
    }

    public function test_admin_can_update_a_destination(): void
    {
        $this->fakeReachable();
        $dest = BackupDestination::create([
            'name' => 'Old', 'driver' => 's3',
            'config' => ['bucket' => 'old-bucket', 'secret' => 'existing-secret'],
        ]);

        $this->putJson('/api/v1/backup/destinations/'.$dest->id, [
            'name' => 'Updated',
            'driver' => 's3',
            'bucket' => 'new-bucket',
            'secret' => '',   // blank → keep existing
        ], ['Authorization' => 'Bearer '.$this->adminToken()])->assertOk();

        $fresh = $dest->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Updated', $fresh->name);
        $freshConfig = $fresh->config;
        $this->assertIsArray($freshConfig);
        $this->assertSame('new-bucket', $freshConfig['bucket']);
        // Blank secret field must NOT clobber the stored value.
        $this->assertSame('existing-secret', $freshConfig['secret']);
    }

    public function test_admin_can_delete_a_destination(): void
    {
        $this->fakeReachable();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $this->deleteJson('/api/v1/backup/destinations/'.$dest->id, [], [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertNoContent();

        $this->assertNull(BackupDestination::find($dest->id));
    }

    public function test_metadata_url_is_rejected_as_endpoint(): void
    {
        // assertReachable runs factory->test() first; stub it to avoid SSRF.
        // The SSRF guard fires in validateDestination before factory->test() is
        // called, so even a non-stubbed call should be blocked by SafeUrl, but
        // we stub anyway to keep the test side-effect-free.
        $this->mock(BackupDestinationFactory::class, function (MockInterface $mock): void {
            /** @var Expectation $e */
            $e = $mock->shouldReceive('test');
            $e->never();
        });

        $this->postJson('/api/v1/backup/destinations', array_merge($this->destPayload(), [
            'endpoint' => 'http://169.254.169.254/latest/meta-data/',
        ]), ['Authorization' => 'Bearer '.$this->adminToken()])
            ->assertUnprocessable();

        $this->assertSame(0, BackupDestination::count());
    }

    /* ------------------------------------------------------------------ */
    /*  Jobs — credential hygiene + db-encrypt invariant */
    /* ------------------------------------------------------------------ */

    public function test_jobs_list_never_includes_passphrase(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        BackupJob::create([
            'name' => 'Secret Job',
            'source' => 'database',
            'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *',
            'retention' => 3,
            'encrypt' => true,
            'passphrase' => 'do-not-leak-this-passphrase',
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/backup/jobs', [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk();

        $response->assertJsonStructure(['jobs' => [[
            'id', 'name', 'source', 'mode', 'destination_id',
            'cron', 'retention', 'encrypt', 'enabled',
            'statistics' => ['runs', 'ok', 'failed'],
        ]]]);
        $response->assertJsonMissing(['passphrase']);
        $response->assertJsonMissing(['do-not-leak-this-passphrase']);
    }

    public function test_database_job_without_encrypt_is_allowed(): void
    {
        // Operator opt-out (2026-08-02): an unencrypted DB backup is permitted — no
        // passphrase required — though the UI warns about the off-box key exposure.
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $this->postJson('/api/v1/backup/jobs', [
            'name' => 'Unencrypted DB',
            'source' => 'database',
            'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *',
            'retention' => 3,
            'encrypt' => false,
        ], ['Authorization' => 'Bearer '.$this->adminToken()])
            ->assertCreated();

        $job = BackupJob::firstWhere('name', 'Unencrypted DB');
        $this->assertNotNull($job);
        $this->assertFalse($job->encrypt);
    }

    public function test_admin_can_create_a_job(): void
    {
        Queue::fake();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);

        $response = $this->postJson('/api/v1/backup/jobs', $this->jobPayload($dest->id), [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertCreated();

        $response->assertJsonPath('job.name', 'Nightly DB');
        $response->assertJsonPath('job.source', 'database');
        $response->assertJsonPath('job.encrypt', true);
        $response->assertJsonMissing(['passphrase']);

        $job = BackupJob::firstOrFail();
        $this->assertSame('a-very-long-passphrase-for-testing', $job->passphrase);
        // Encrypted at rest.
        $rawPassphrase = \DB::table('backup_jobs')->value('passphrase');
        $this->assertStringNotContainsString(
            'a-very-long-passphrase-for-testing',
            is_string($rawPassphrase) ? $rawPassphrase : '',
        );
    }

    public function test_update_job_blank_passphrase_keeps_existing(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create(array_merge($this->jobPayload($dest->id), ['passphrase' => 'original-passphrase-12']));

        $this->putJson('/api/v1/backup/jobs/'.$job->id, array_merge($this->jobPayload($dest->id), [
            'passphrase' => '',   // blank → keep stored value
        ]), ['Authorization' => 'Bearer '.$this->adminToken()])->assertOk();

        $freshJob = $job->fresh();
        $this->assertNotNull($freshJob);
        $this->assertSame('original-passphrase-12', $freshJob->passphrase);
    }

    public function test_admin_can_delete_a_job(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create($this->jobPayload($dest->id));

        $this->deleteJson('/api/v1/backup/jobs/'.$job->id, [], [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertNoContent();

        $this->assertNull(BackupJob::find($job->id));
    }

    /* ------------------------------------------------------------------ */
    /*  Run now */
    /* ------------------------------------------------------------------ */

    public function test_run_now_dispatches_job(): void
    {
        Queue::fake();
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create($this->jobPayload($dest->id));

        $this->postJson('/api/v1/backup/jobs/'.$job->id.'/run', [], [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk()->assertJsonPath('ok', true);

        Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $j) => $j->backupJobId === $job->id);
    }

    /* ------------------------------------------------------------------ */
    /*  Runs list */
    /* ------------------------------------------------------------------ */

    public function test_runs_list_has_expected_shape(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create($this->jobPayload($dest->id));
        BackupRun::create([
            'backup_job_id' => $job->id,
            'status' => 'success',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'bytes' => 1024,
            'filename' => 'backup-2026-07-29.sql.gz.enc',
        ]);

        $response = $this->getJson('/api/v1/backup/runs', [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk();

        $response->assertJsonStructure(['runs' => [[
            'id', 'job', 'status', 'startedIso', 'size',
            'downloadable', 'encrypted', 'cancellable', 'verifiable',
        ]]]);
        $response->assertJsonPath('runs.0.status', 'success');
        $response->assertJsonPath('runs.0.encrypted', true);
        $response->assertJsonPath('runs.0.downloadable', true);
    }

    /* ------------------------------------------------------------------ */
    /*  Cancel run */
    /* ------------------------------------------------------------------ */

    public function test_cancel_run_sets_cancel_requested(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create($this->jobPayload($dest->id));
        $run = BackupRun::create([
            'backup_job_id' => $job->id,
            'status' => 'running',
            'started_at' => now(),
            'cancel_requested' => false,
        ]);

        $this->postJson('/api/v1/backup/runs/'.$run->id.'/cancel', [], [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk()->assertJsonPath('forced', false);

        $freshRun = $run->fresh();
        $this->assertNotNull($freshRun);
        $this->assertTrue((bool) $freshRun->cancel_requested);
    }

    public function test_second_cancel_force_finalises_run(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create($this->jobPayload($dest->id));
        $run = BackupRun::create([
            'backup_job_id' => $job->id,
            'status' => 'running',
            'started_at' => now(),
            'cancel_requested' => true,
        ]);

        $this->postJson('/api/v1/backup/runs/'.$run->id.'/cancel', [], [
            'Authorization' => 'Bearer '.$this->adminToken(),
        ])->assertOk()->assertJsonPath('forced', true);

        $freshRun2 = $run->fresh();
        $this->assertNotNull($freshRun2);
        $this->assertSame('cancelled', $freshRun2->status);
    }
}
