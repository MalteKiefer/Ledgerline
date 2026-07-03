<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_run_statistics(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'J', 'source' => 'database', 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 5, 'notify' => 'none', 'enabled' => true,
        ]);

        // Two successes (10s / 20s, 100 / 300 bytes) and one failure.
        BackupRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()->subMinutes(30), 'finished_at' => now()->subMinutes(30)->addSeconds(10), 'bytes' => 100]);
        BackupRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(10)->addSeconds(20), 'bytes' => 300]);
        BackupRun::create(['backup_job_id' => $job->id, 'status' => 'failed', 'started_at' => now()->subMinutes(5), 'finished_at' => now()->subMinutes(5)->addSeconds(2)]);

        $s = $job->statistics();

        $this->assertSame(3, $s['runs']);
        $this->assertSame(2, $s['ok']);
        $this->assertSame(1, $s['failed']);
        $this->assertSame(67, $s['successRate']);
        $this->assertSame('failed', $s['lastStatus']);
        $this->assertSame(20, $s['lastDuration']); // most recent success
        $this->assertSame(15, $s['avgDuration']);  // (10 + 20) / 2
        $this->assertSame(300, $s['lastBytes']);
        $this->assertSame(400, $s['totalBytes']);
        $this->assertNotNull($s['nextRun']);
    }

    public function test_a_job_with_no_runs_has_empty_stats(): void
    {
        $dest = BackupDestination::create(['name' => 'D', 'driver' => 's3', 'config' => []]);
        $job = BackupJob::create([
            'name' => 'J', 'source' => 'files', 'backup_destination_id' => $dest->id,
            'cron' => '0 3 * * *', 'retention' => 5, 'notify' => 'none', 'enabled' => true,
        ]);

        $s = $job->statistics();

        $this->assertSame(0, $s['runs']);
        $this->assertNull($s['successRate']);
        $this->assertSame(0, $s['totalBytes']);
    }
}
