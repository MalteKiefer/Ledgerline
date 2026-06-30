<?php

declare(strict_types=1);

namespace App\Providers;

use App\Search\SearchManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\PocketID\Provider as PocketIdProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Build the global-search manager from the configured providers, so
        // adding a searchable entity is just a config + provider-class change.
        $this->app->singleton(SearchManager::class, function ($app): SearchManager {
            $providers = array_map(
                static fn (string $class) => $app->make($class),
                config('search.providers', []),
            );

            return new SearchManager($providers, (int) config('search.limit_per_group', 8));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the Pocket-ID OIDC driver with Socialite. Laravel 11+ has no
        // EventServiceProvider, so the listener is wired up here.
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('pocketid', PocketIdProvider::class);
        });
    }
}
