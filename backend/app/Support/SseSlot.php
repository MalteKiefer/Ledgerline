<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A small, self-expiring per-user concurrency limiter for long-lived
 * blocking connections — currently only FilesChangesController's SSE
 * stream.
 *
 * Why this exists: the app runs under Laravel Octane (FrankenPHP,
 * `--workers=auto` — resolves to the host's CPU count, 4 on the production
 * box), a FIXED pool of persistent workers, not a per-request-forked
 * classic PHP-FPM pool. An SSE stream deliberately blocks one worker for
 * its whole lifetime. Without a cap, enough concurrent connections (several
 * sync-client devices, one runaway/reconnect-looping client, ...) could
 * exhaust every worker — making the ENTIRE app, every other route, not just
 * this one, unresponsive for as long as those connections stay open.
 *
 * This bounds that with a small numbered pool of CAP slots per user:
 * acquire() claims one atomically ("set if not exists" via Cache::add),
 * heartbeat() refreshes it on every poll tick so a normally-running stream
 * never loses its own slot, and release() frees it explicitly on any exit
 * path. A held slot ALSO self-expires via its own short TTL if the holder
 * never gets to call release() (a killed worker, an uncaught fatal,
 * --max-requests recycling mid-stream) — a crash can leak a slot for at
 * most one missed heartbeat, never forever.
 *
 * Driver-portable like FileChangeSignal (Cache facade, not a Redis-specific
 * SCAN over a key pattern) — works identically against the `array` store in
 * tests as it does against redis in production.
 */
final class SseSlot
{
    /** How many concurrent streams one user may hold open at once. */
    public const CAP = 2;

    private static function key(int $userId, int $slot): string
    {
        return "sse-slot:{$userId}:{$slot}";
    }

    /**
     * Claims the first free slot (0..CAP-1) for $userId, or null when every
     * slot is already held (the caller should reject the connection rather
     * than open it). $ttlSeconds should comfortably exceed the gap between
     * two heartbeat() calls.
     */
    public static function acquire(int $userId, int $ttlSeconds): ?int
    {
        for ($slot = 0; $slot < self::CAP; $slot++) {
            if (Cache::add(self::key($userId, $slot), true, $ttlSeconds)) {
                return $slot;
            }
        }

        return null;
    }

    /** Extends a held slot's lease — call on every poll tick so a live stream never expires its own slot. */
    public static function heartbeat(int $userId, int $slot, int $ttlSeconds): void
    {
        Cache::put(self::key($userId, $slot), true, $ttlSeconds);
    }

    /** Frees a held slot immediately (loop end, disconnect, exception) instead of waiting out its TTL. */
    public static function release(int $userId, int $slot): void
    {
        Cache::forget(self::key($userId, $slot));
    }

    private function __construct() {}
}
