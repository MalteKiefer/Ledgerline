<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\StorageSnapshot;
use App\Services\Ops\StorageHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StorageHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_is_idempotent_per_day(): void
    {
        $history = app(StorageHistory::class);
        $history->capture();
        $history->capture();

        $this->assertSame(1, StorageSnapshot::count());
        $this->assertSame(Carbon::today()->toDateString(), StorageSnapshot::first()->captured_on->toDateString());
    }

    public function test_the_snapshot_command_records_a_row(): void
    {
        $this->artisan('ops:snapshot-storage')->assertSuccessful();

        $this->assertSame(1, StorageSnapshot::count());
    }

    public function test_trend_reports_growth_delta(): void
    {
        StorageSnapshot::create(['captured_on' => Carbon::today()->subDays(10)->toDateString(), 'total_bytes' => 100]);
        StorageSnapshot::create(['captured_on' => Carbon::today()->toDateString(), 'total_bytes' => 500]);

        $trend = app(StorageHistory::class)->trend(30);

        $this->assertCount(2, $trend['points']);
        $this->assertSame(400, $trend['deltaBytes']);
        $this->assertSame(10, $trend['deltaDays']);
    }
}
