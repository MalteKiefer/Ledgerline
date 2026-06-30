<?php

declare(strict_types=1);

namespace App\Providers;

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
        //
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
