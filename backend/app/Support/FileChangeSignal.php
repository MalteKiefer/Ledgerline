<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A per-user "something in Files changed" marker, polled by
 * FilesChangesController's SSE loop so a sync client notices a remote
 * change within a couple of seconds instead of waiting for its own fallback
 * interval (ledgerline-cli's `files sync --interval`, 5 minutes by
 * default). Deliberately just a timestamp, not an event log: a client that
 * gets woken up runs its own normal diff sync pass, which already knows
 * exactly what changed — this only ever needs to say "something did, go
 * look," so there is nothing to reconcile if two changes land in the same
 * poll tick.
 *
 * Goes through the Cache facade (CACHE_STORE=redis in production, `array`
 * in tests — see phpunit.xml) rather than the Redis facade directly: same
 * real Redis-backed behaviour in production, but tests exercising
 * App\Observers\FileChangeObserver don't need a live Redis connection.
 */
final class FileChangeSignal
{
    /** Idle marker lifetime — long enough that no in-progress SSE poll ever misses it, short enough not to linger forever unused. */
    private const TTL_SECONDS = 3600;

    private static function key(int $userId): string
    {
        return "files-changed:{$userId}";
    }

    /** Called by App\Observers\FileChangeObserver on every FileEntry/FileFolder create/update/delete/restore. */
    public static function touch(int $userId): void
    {
        Cache::put(self::key($userId), microtime(true), self::TTL_SECONDS);
    }

    /** The microtime(true) of the last touch() for $userId, or null if there hasn't been one (or it expired). */
    public static function lastChangedAt(int $userId): ?float
    {
        $v = Cache::get(self::key($userId));

        return is_numeric($v) ? (float) $v : null;
    }

    private function __construct() {}
}
