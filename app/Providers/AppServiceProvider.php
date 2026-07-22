<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dev tripwire: surface any accidental N+1 (a relation lazy-loaded in a
        // loop) as a loud exception while developing. Local only — never in prod
        // and never in the test env, so it can't mask a real failure with a
        // lazy-load error.
        Model::preventLazyLoading(app()->environment('local'));

        // Register the Pocket-ID OIDC driver with Socialite. Laravel 11+ has no
        // EventServiceProvider, so the listener is wired up here.
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('pocketid', PocketIdProvider::class);
        });

        // Only members of the configured Pocket-ID admin group (if any) may
        // manage the non-personal, workspace-wide settings.
        Gate::define('manage-global-settings', fn (User $user): bool => $user->managesGlobalSettings());

        // Hard, IP-keyed limit on the public QR-pairing exchange (the one-time
        // code is the only credential there) — tight, since a legitimate user
        // pairs a handful of devices by hand.
        RateLimiter::for('auth-pair', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Shared-vault recipient public-key lookup. Keyed by authenticated user
        // id to prevent bulk enumeration of registered public keys; falls back
        // to IP for unauthenticated callers (should never happen on this route).
        RateLimiter::for('pubkey-lookup', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        $this->applySettingOverrides();

        // Record each scheduled maintenance task's last run + outcome so the
        // System settings page can show whether the cron is alive.
        Event::listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $e) => self::recordCronRun($e->task, true));
        Event::listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $e) => self::recordCronRun($e->task, false));
    }

    /** Cache key holding the last run for a scheduled command. */
    public static function cronRunKey(string $name): string
    {
        return 'cron:last:'.$name;
    }

    /** Extract the artisan command name from a scheduled Event (or its summary). */
    public static function cronName(object $event): string
    {
        $command = $event->command ?? null;
        $command = is_scalar($command) ? (string) $command : '';
        if (preg_match('/artisan[\'"]?\s+([a-z0-9:_-]+)/i', $command, $m) === 1) {
            return $m[1];
        }

        if (method_exists($event, 'getSummaryForDisplay')) {
            $summary = $event->getSummaryForDisplay();

            return is_string($summary) ? $summary : 'task';
        }

        return 'task';
    }

    private static function recordCronRun(object $event, bool $ok): void
    {
        Cache::put(self::cronRunKey(self::cronName($event)), [
            'at' => now()->toIso8601String(),
            'ok' => $ok,
        ], now()->addDays(30));
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
        'files_blob_orphan_grace_hours' => ['files.blob_orphan_grace_hours', 'int'],
        'gallery_ml_enabled' => ['gallery.ml_enabled', 'bool'],
        'gallery_ml_url' => ['gallery.ml_url', 'string'],
        'gallery_ml_clip_model' => ['gallery.ml_clip_model', 'string'],
        'gallery_face_enabled' => ['gallery.face_enabled', 'bool'],
        'gallery_face_model' => ['gallery.face_model', 'string'],
        // NB: the ffmpeg/exiftool BINARY paths are intentionally NOT overridable
        // from the DB/UI — a settable executable path is a remote-code-execution
        // lever. They stay env/config-only.
        'gallery_face_min_score' => ['gallery.face_min_score', 'float'],
        'gallery_geocode_interval_ms' => ['gallery.geocode_interval_ms', 'int'],
    ];

    public const OVERRIDES_CACHE_KEY = 'app-settings:overrides';

    /**
     * Overlay admin settings onto config. Cached (settings saves clear it) so it
     * adds no DB query per request.
     */
    private function applySettingOverrides(): void
    {
        // Wrapped: this runs in boot() for every context including the docker
        // build's `package:discover`, where there is no database or cache — a
        // failure there must not break the build; config just keeps its defaults.
        try {
            $values = Cache::remember(self::OVERRIDES_CACHE_KEY, 3600, function (): array {
                if (! Schema::hasTable('app_settings')) {
                    return [];
                }
                $row = DB::table('app_settings')->first(array_keys(self::SETTING_OVERRIDES));

                return $row ? array_filter((array) $row, fn ($v) => $v !== null) : [];
            });
        } catch (\Throwable) {
            return;
        }

        foreach (self::SETTING_OVERRIDES as $col => [$cfg, $type]) {
            if (! isset($values[$col])) {
                continue;
            }
            $v = $values[$col];
            config([$cfg => match ($type) {
                'int' => is_numeric($v) ? (int) $v : 0,
                'float' => is_numeric($v) ? (float) $v : 0.0,
                'bool' => (bool) $v,
                default => is_scalar($v) ? (string) $v : '',
            }]);
        }
    }
}
