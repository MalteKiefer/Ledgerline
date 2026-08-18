<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A small, self-expiring concurrency limiter for long-lived blocking
 * connections — currently only FilesChangesController's SSE stream.
 *
 * Why this exists: the app runs under Laravel Octane (FrankenPHP), a FIXED
 * pool of persistent workers (see the Dockerfile's `octane:frankenphp
 * --workers=...`), not a per-request-forked classic PHP-FPM pool. An SSE
 * stream deliberately blocks one worker for its whole lifetime.
 *
 * TWO caps, not one — this is a multi-user, multi-device system, and a
 * per-user cap alone does not bound total exposure:
 *
 *  - CAP (per user) stops one account's own devices — several
 *    ledgerline-cli instances, a runaway reconnect loop — from claiming
 *    more than a handful of workers by itself.
 *
 *  - CAP_GLOBAL (across every user combined) is the one that actually
 *    matters in production: found the hard way — a single background
 *    ledgerline-cli instance chronically held 1-2 of the app's then-4
 *    total workers via its reconnect loop (steady-state, by design, not a
 *    bug in the stream itself), and a handful of concurrent browser
 *    requests exhausted what little was left, making the whole app
 *    unresponsive. A per-user cap does nothing against that: a modest
 *    number of DIFFERENT users each running the CLI on a couple of devices
 *    can just as easily reserve every worker the app has between them.
 *    CAP_GLOBAL is a small, fixed, ABSOLUTE ceiling — deliberately NOT a
 *    fraction of the configured worker count — so "at least (workers −
 *    CAP_GLOBAL) are always free for ordinary web/API requests" holds no
 *    matter how many users or devices are running the CLI, and regardless
 *    of how the worker pool itself is later resized. Losing a slot to
 *    either cap is never a functional loss for the client:
 *    ledgerline-cli already falls back to its own 5-minute poll interval
 *    whenever it can't get (or loses) a stream — this only ever affects
 *    how fast it notices a remote change, never correctness.
 *
 * acquire() claims one slot of EACH kind atomically ("set if not exists"
 * via Cache::add — see the giveback note on acquire() itself for what
 * happens when only one of the two succeeds), heartbeat() refreshes both on
 * every poll tick so a normally-running stream never loses either, and
 * release() frees both explicitly on any exit path. A held slot also
 * self-expires via its own short TTL if the holder never gets to call
 * release() (a killed worker, an uncaught fatal, --max-requests recycling
 * mid-stream) — a crash can leak a slot for at most one missed heartbeat,
 * never forever.
 *
 * Driver-portable like FileChangeSignal (Cache facade, not a Redis-specific
 * SCAN over a key pattern) — works identically against the `array` store in
 * tests as it does against redis in production.
 */
final class SseSlot
{
    /** How many concurrent streams one user may hold open at once. */
    public const CAP = 2;

    /**
     * How many concurrent streams ALL users combined may hold open at once —
     * see the class doc comment for why this exists on top of CAP.
     */
    public const CAP_GLOBAL = 4;

    private static function key(int $userId, int $slot): string
    {
        return "sse-slot:{$userId}:{$slot}";
    }

    private static function globalKey(int $slot): string
    {
        return "sse-slot:global:{$slot}";
    }

    /**
     * Claims the first free per-user slot AND the first free global slot for
     * $userId, or null when either pool is exhausted (the caller should
     * reject the connection rather than open it). $ttlSeconds should
     * comfortably exceed the gap between two heartbeat() calls.
     *
     * The two claims are not one atomic operation (Cache::add only
     * guarantees atomicity per key) — if the global claim succeeds but this
     * user's own cap turns out to already be full, the global slot is handed
     * straight back rather than left to idle out its TTL, so an
     * already-saturated user's repeated attempts can never slowly starve the
     * global pool for everyone else.
     *
     * @return array{user: int, global: int}|null
     */
    public static function acquire(int $userId, int $ttlSeconds): ?array
    {
        $global = null;
        for ($g = 0; $g < self::CAP_GLOBAL; $g++) {
            if (Cache::add(self::globalKey($g), true, $ttlSeconds)) {
                $global = $g;
                break;
            }
        }
        if ($global === null) {
            return null; // every worker the app is willing to lend to SSE is already spoken for
        }

        for ($u = 0; $u < self::CAP; $u++) {
            if (Cache::add(self::key($userId, $u), true, $ttlSeconds)) {
                return ['user' => $u, 'global' => $global];
            }
        }
        Cache::forget(self::globalKey($global));

        return null;
    }

    /**
     * Extends a held slot pair's lease — call on every poll tick so a live
     * stream never expires either of its slots.
     *
     * @param  array{user: int, global: int}  $slot
     */
    public static function heartbeat(int $userId, array $slot, int $ttlSeconds): void
    {
        Cache::put(self::key($userId, $slot['user']), true, $ttlSeconds);
        Cache::put(self::globalKey($slot['global']), true, $ttlSeconds);
    }

    /**
     * Frees a held slot pair immediately (loop end, disconnect, exception)
     * instead of waiting out its TTL.
     *
     * @param  array{user: int, global: int}  $slot
     */
    public static function release(int $userId, array $slot): void
    {
        Cache::forget(self::key($userId, $slot['user']));
        Cache::forget(self::globalKey($slot['global']));
    }

    private function __construct() {}
}
