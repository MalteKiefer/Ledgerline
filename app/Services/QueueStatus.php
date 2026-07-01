<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * A best-effort snapshot of the queue: how many jobs are waiting and how many
 * have failed. The number of running workers and any completion estimate are
 * not tracked by a plain queue worker (that requires Horizon), so they are not
 * reported here.
 */
class QueueStatus
{
    /**
     * @return array{pending: ?int, failed: int, available: bool}
     */
    public function snapshot(): array
    {
        $pending = $this->pending();

        return [
            'pending' => $pending,
            'failed' => $this->failed(),
            'available' => $pending !== null,
        ];
    }

    /**
     * Number of jobs waiting on the default queue, or null if the queue backend
     * cannot be reached or does not support sizing.
     */
    private function pending(): ?int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return null;
        }
    }

    private function failed(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
