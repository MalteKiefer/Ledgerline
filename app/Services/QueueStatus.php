<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * A best-effort snapshot of the queue: which connection is in use, how many
 * jobs are waiting and how many have failed.
 *
 * The number of running workers and any completion estimate are not tracked
 * in-app — a plain worker registers nothing, and a managed queue (e.g. Laravel
 * Cloud) reports those in its own dashboard. Some drivers (sync, null, and some
 * managed connections) also cannot report a pending count, so it may be null.
 */
class QueueStatus
{
    /**
     * Drivers that cannot report a waiting-job count.
     *
     * @var list<string>
     */
    private const UNCOUNTABLE = ['sync', 'null'];

    /**
     * @return array{connection: string, driver: string, pending: ?int, failed: int}
     */
    public function snapshot(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        return [
            'connection' => $connection,
            'driver' => $driver,
            'pending' => $this->pending($driver),
            'failed' => $this->failed(),
        ];
    }

    /**
     * Number of jobs waiting on the default queue, or null when the driver
     * cannot report it or the backend cannot be reached.
     */
    private function pending(string $driver): ?int
    {
        if (in_array($driver, self::UNCOUNTABLE, true)) {
            return null;
        }

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
