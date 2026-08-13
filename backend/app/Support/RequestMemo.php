<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * Per-request memoisation store.
 *
 * Some rows (the workspace AppSettings, a user's UserSetting) are read several
 * times while rendering one request; memoising them avoids repeat queries. This
 * used to live in the service container (`app()->instance()`), which the classic
 * FPM lifecycle threw away after every request. Under a persistent Octane worker
 * the container is long-lived, so a container memo would (a) grow unbounded — one
 * cached instance per user that ever hit the worker — and (b) go stale after a
 * settings change made on another worker. This static store is flushed at the
 * START of every Octane request (FlushRequestMemo listener) and between tests
 * (TestCase::setUp), so a memo never outlives the request that created it.
 */
final class RequestMemo
{
    /** @var array<string, mixed> */
    private static array $store = [];

    /**
     * Return the memoised value for $key, computing + caching it on first use.
     *
     * @template T
     *
     * @param  Closure(): T  $make
     * @return T
     */
    public static function remember(string $key, Closure $make): mixed
    {
        if (! array_key_exists($key, self::$store)) {
            self::$store[$key] = $make();
        }

        /** @var T */
        return self::$store[$key];
    }

    /** Drop a single memoised entry (e.g. after replacing the underlying row). */
    public static function forget(string $key): void
    {
        unset(self::$store[$key]);
    }

    /** Clear the whole store — called per Octane request and between tests. */
    public static function flush(): void
    {
        self::$store = [];
    }
}
