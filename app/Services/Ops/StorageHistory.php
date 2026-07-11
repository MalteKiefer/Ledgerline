<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\StorageSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Captures and reads the daily storage-usage history that powers the System
 * page's growth trend. Capture is idempotent per day (one row), so it is safe
 * to run from the scheduler and on demand when the page is viewed.
 */
class StorageHistory
{
    public function __construct(private readonly SystemStatus $status) {}

    /** Record today's usage (idempotent — updates the existing row for today). */
    public function capture(): StorageSnapshot
    {
        $storage = $this->status->snapshot()['storage'];

        // Use a Carbon key (not a date string) so the lookup value serialises
        // through the date cast identically to what create() stores — otherwise
        // '2026-01-01' never matches the stored '2026-01-01 00:00:00'.
        return StorageSnapshot::updateOrCreate(
            ['captured_on' => Carbon::today()],
            [
                'files_bytes' => $storage['files'],
                'gallery_bytes' => $storage['gallery'],
                'database_bytes' => $storage['database'],
                'total_bytes' => $storage['total'],
            ],
        );
    }

    /**
     * Trend over the last $days days: the point series (for a sparkline) and the
     * growth delta per module.
     *
     * @return array{
     *   points: list<array{date: string, total: int}>,
     *   deltaBytes: int,
     *   deltaDays: int
     * }
     */
    public function trend(int $days = 30): array
    {
        $since = CarbonImmutable::today()->subDays($days)->toDateString();

        $rows = StorageSnapshot::where('captured_on', '>=', $since)
            ->orderBy('captured_on')
            ->get();

        $points = $rows->map(fn (StorageSnapshot $s): array => [
            'date' => $s->captured_on->toDateString(),
            'total' => $s->total_bytes,
        ])->all();

        $first = $rows->first();
        $last = $rows->last();
        $deltaBytes = ($first && $last) ? ($last->total_bytes - $first->total_bytes) : 0;
        $deltaDays = ($first && $last) ? (int) $first->captured_on->diffInDays($last->captured_on) : 0;

        return [
            'points' => $points,
            'deltaBytes' => $deltaBytes,
            'deltaDays' => $deltaDays,
        ];
    }
}
