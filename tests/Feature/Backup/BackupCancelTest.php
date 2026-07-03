<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupJob;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupCancelTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(array $attrs = []): BackupRun
    {
        $job = BackupJob::create([
            'name' => 'Files', 'source' => 'files', 'backup_destination_id' => null,
            'cron' => '0 3 * * *', 'retention' => 3, 'notify_channels' => [], 'enabled' => true,
        ]);

        return $job->runs()->create(array_merge(['status' => 'running', 'started_at' => now()], $attrs));
    }

    public function test_first_cancel_requests_a_graceful_stop(): void
    {
        $this->signIn();
        $run = $this->makeRun();

        $this->postJson(route('settings.backup.runs.cancel', $run))
            ->assertOk()->assertJson(['ok' => true, 'forced' => false]);

        $run->refresh();
        $this->assertSame('running', $run->status);
        $this->assertTrue($run->cancel_requested);
    }

    public function test_second_cancel_force_stops(): void
    {
        $this->signIn();
        $run = $this->makeRun(['cancel_requested' => true]);

        $this->postJson(route('settings.backup.runs.cancel', $run))
            ->assertOk()->assertJson(['ok' => true, 'forced' => true]);

        $this->assertSame('cancelled', $run->refresh()->status);
    }

    public function test_runs_reaps_a_stale_cancelled_run(): void
    {
        $this->signIn();
        $run = $this->makeRun(['cancel_requested' => true]);
        // Simulate no progress for a while (worker gone).
        DB::table('backup_runs')->where('id', $run->id)->update(['updated_at' => now()->subMinutes(5)]);

        $this->getJson(route('settings.backup.runs'))->assertOk();

        $this->assertSame('cancelled', $run->refresh()->status);
    }

    public function test_runs_reaps_an_orphaned_running_run(): void
    {
        $this->signIn();
        $run = $this->makeRun();
        DB::table('backup_runs')->where('id', $run->id)->update(['updated_at' => now()->subMinutes(45)]);

        $this->getJson(route('settings.backup.runs'))->assertOk();

        $this->assertSame('failed', $run->refresh()->status);
    }

    public function test_a_healthy_running_run_is_not_reaped(): void
    {
        $this->signIn();
        $run = $this->makeRun(); // updated just now

        $this->getJson(route('settings.backup.runs'))->assertOk();

        $this->assertSame('running', $run->refresh()->status);
    }
}
