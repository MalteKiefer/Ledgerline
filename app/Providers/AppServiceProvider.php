<?php

declare(strict_types=1);

namespace App\Providers;

use App\Dav\AuthBackend;
use App\Dav\DavContext;
use App\Events\PersonNamed;
use App\Listeners\LinkPersonContact;
use App\Models\User;
use App\Search\SearchManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // Rate-limit the DAV endpoint. The generous quota is granted ONLY when
        // the presented Basic credentials recently passed a real bcrypt check
        // (marker set by AuthBackend) — an attacker cannot forge the Basic header
        // to escape the tight bucket. Everything else (no auth, wrong password)
        // stays at 60/min/IP to blunt bcrypt brute-forcing. The generous quota
        // exists because macOS Finder fires hundreds of PROPFIND/LOCK/PUT per
        // copy and a flat 60/min returns 429 → Finder error -50.
        RateLimiter::for('dav', function ($request) {
            $user = $request->getUser();
            $pass = $request->getPassword();
            if ($user !== null && $pass !== null
                && Cache::get(AuthBackend::authMarkerKey($user, $pass)) !== null) {
                return Limit::perMinute(2000)->by('dav-user:'.$user);
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        // Only members of the configured Pocket-ID admin group (if any) may
        // manage the non-personal, workspace-wide settings.
        Gate::define('manage-global-settings', fn (User $user): bool => $user->managesGlobalSettings());
    }
}
