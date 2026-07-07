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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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

        $this->applySettingOverrides();
    }

    /**
     * Admin-configured global overrides applied over the config/env defaults.
     * Each entry: db column => [config key, type]. A null column keeps the
     * built-in default. The Settings saves clear the cache key below.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SETTING_OVERRIDES = [
        'files_quota_mb' => ['files.quota_mb', 'int'],
        'files_max_upload_mb' => ['files.max_upload_mb', 'int'],
        'files_trash_retention_days' => ['files.trash_retention_days', 'int'],
        'files_archive_max_entries' => ['files.archive_max_entries', 'int'],
        'files_archive_max_mb' => ['files.archive_max_mb', 'int'],
        'files_blob_orphan_grace_hours' => ['files.blob_orphan_grace_hours', 'int'],
        'gallery_ml_enabled' => ['gallery.ml_enabled', 'bool'],
        'gallery_ml_url' => ['gallery.ml_url', 'string'],
        'gallery_ml_clip_model' => ['gallery.ml_clip_model', 'string'],
        'gallery_face_enabled' => ['gallery.face_enabled', 'bool'],
        'gallery_face_model' => ['gallery.face_model', 'string'],
        'gallery_ffmpeg_path' => ['gallery.ffmpeg_path', 'string'],
        'gallery_exiftool_path' => ['gallery.exiftool_path', 'string'],
        'gallery_duplicate_threshold' => ['gallery.duplicate_threshold', 'float'],
        'gallery_phash_max_distance' => ['gallery.phash_max_distance', 'int'],
        'gallery_face_min_score' => ['gallery.face_min_score', 'float'],
        'gallery_face_min_size' => ['gallery.face_min_size', 'int'],
        'gallery_face_cluster_threshold' => ['gallery.face_cluster_threshold', 'float'],
        'gallery_face_min_per_person' => ['gallery.face_min_per_person', 'int'],
        'gallery_geocode_interval_ms' => ['gallery.geocode_interval_ms', 'int'],
    ];

    public const OVERRIDES_CACHE_KEY = 'app-settings:overrides';

    /**
     * Overlay admin settings onto config. Cached (settings saves clear it) so it
     * adds no DB query per request — important for the high-frequency DAV path.
     */
    private function applySettingOverrides(): void
    {
        $values = Cache::remember(self::OVERRIDES_CACHE_KEY, 3600, function (): array {
            if (! Schema::hasTable('app_settings')) {
                return [];
            }
            $row = DB::table('app_settings')->first(array_keys(self::SETTING_OVERRIDES));

            return $row ? array_filter((array) $row, fn ($v) => $v !== null) : [];
        });

        foreach (self::SETTING_OVERRIDES as $col => [$cfg, $type]) {
            if (! isset($values[$col])) {
                continue;
            }
            $v = $values[$col];
            config([$cfg => match ($type) {
                'int' => (int) $v,
                'float' => (float) $v,
                'bool' => (bool) $v,
                default => (string) $v,
            }]);
        }
    }
}
