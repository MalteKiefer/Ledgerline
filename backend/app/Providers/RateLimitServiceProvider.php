<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\RateLimiters;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the app's named HTTP rate limiters (see App\Support\RateLimiters).
 *
 * Boot-churn trap (why this is a dedicated provider that registers in booted()):
 * the Illuminate\Cache\RateLimiter singleton is bound by the DEFERRED
 * CacheServiceProvider, which also owns `cache`. Historically the limiters were
 * defined in AppServiceProvider::boot() BEFORE that provider read settings via the
 * cache; defining them before the cache/RateLimiter singleton was fully resolved
 * left them on a throwaway instance, so at request time Fortify failed with
 * "Rate limiter [fortify] is not defined" (a live 500 on /login).
 *
 * Fix: register inside $app->booted() — which runs AFTER every provider's boot()
 * (so AppServiceProvider has already resolved the cache) — and force-resolve
 * `cache` first so the deferred CacheServiceProvider is fully loaded and the
 * RateLimiter singleton is its final instance. Verified empirically: resolving
 * `cache`/`session` after this point does NOT rebind the RateLimiter singleton,
 * so the limiters survive to request time. This provider is NON-deferred and
 * registered in bootstrap/providers.php.
 */
final class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            // Force the deferred CacheServiceProvider fully loaded so the limiters
            // land on the final Illuminate\Cache\RateLimiter singleton.
            $this->app->make('cache');
            RateLimiters::register();
        });
    }
}
