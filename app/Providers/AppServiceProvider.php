<?php

declare(strict_types=1);

namespace App\Providers;

use App\Dav\DavContext;
use App\Events\PersonNamed;
use App\Listeners\LinkPersonContact;
use App\Search\SearchManager;
use App\Services\Mail\ImapReader;
use App\Services\Mail\ImapStats;
use App\Services\Mail\MailSource;
use App\Services\Mail\WebklexImapReader;
use App\Services\Mail\WebklexImapStats;
use App\Services\Mail\WebklexMailSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        // One CardDAV auth context per request; shared by the sabre backends so
        // they can scope every operation to the authenticated user.
        $this->app->scoped(DavContext::class);

        // Build the global-search manager from the configured providers, so
        // adding a searchable entity is just a config + provider-class change.
        $this->app->singleton(SearchManager::class, function ($app): SearchManager {
            $providers = array_map(
                static fn (string $class) => $app->make($class),
                config('search.providers', []),
            );

            return new SearchManager($providers, (int) config('search.limit_per_group', 8));
        });

        // Read-only IMAP statistics provider (pure-PHP, no ext-imap).
        $this->app->bind(ImapStats::class, WebklexImapStats::class);
        $this->app->bind(ImapReader::class, WebklexImapReader::class);
        $this->app->bind(MailSource::class, WebklexMailSource::class);
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

        // Naming a gallery person links/creates a vCard contact (avatar from the
        // person's cover face).
        Event::listen(PersonNamed::class, LinkPersonContact::class);

        // Rate-limit the unauthenticated CardDAV endpoint (bcrypt per request).
        RateLimiter::for('dav', fn ($request) => Limit::perMinute(60)->by($request->ip()));
    }
}
