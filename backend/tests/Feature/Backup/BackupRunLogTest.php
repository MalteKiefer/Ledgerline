<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupJob;
use App\Services\Backup\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRunLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_run_records_a_step_log_with_the_cause(): void
    {
        // A job with no destination fails early; the run log should capture it.
        // Use a file source: a database source is rejected even earlier (it must
        // be encrypted), which would mask the missing-destination path this covers.
        $job = BackupJob::create([
            'name' => 'No dest', 'source' => 'files', 'backup_destination_id' => null,
            'cron' => '0 3 * * *', 'retention' => 3, 'notify_channels' => [], 'enabled' => true,
        ]);

        $run = app(BackupManager::class)->run($job);

        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->log);
        $this->assertStringContainsString('started', $run->log);
        $this->assertStringContainsString('FAILED', $run->log);
        $this->assertStringContainsString('No destination', $run->log);
    }
}
