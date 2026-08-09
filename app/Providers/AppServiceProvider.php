<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AppSettings;
use App\Models\FileEntry;
use App\Models\User;
use App\Observers\FileEntryObserver;
use App\Support\OutboundUrl;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        // Behind a TLS-terminating reverse proxy (e.g. NetBird) that forwards to
        // the app over plain HTTP, Laravel would otherwise generate http:// asset
        // URLs, form actions and redirects — which the https page then blocks as
        // mixed content / CSP scheme mismatches. When FORCE_HTTPS is set, emit
        // every generated URL as https deterministically (independent of whether
        // the proxy sends X-Forwarded-Proto).
        if ((bool) config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Keep a file's searchable text (search_text/indexed_at) in sync with its bytes.
        FileEntry::observe(FileEntryObserver::class);

        // Only admins may manage the non-personal, workspace-wide settings.
        Gate::define('manage-global-settings', fn (User $user): bool => $user->managesGlobalSettings());

        // Rate limiting is disabled app-wide (private 2-user home LAN) — the
        // `throttle` alias is a no-op (App\Http\Middleware\NoThrottle); no named
        // limiters are defined. See the Security register (2026-08-08).
        $this->applySettingOverrides();
        $this->applyMailSettings();

        // Record each scheduled maintenance task's last run + outcome so the
        // System settings page can show whether the cron is alive.
        Event::listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $e) => self::recordCronRun($e->task, true));
        Event::listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $e) => self::recordCronRun($e->task, false));

        // Stamp the last successful login (shown in admin user management). Written
        // quietly so it does not fire model events or bump updated_at.
        Event::listen(Login::class, function (Login $e): void {
            if ($e->user instanceof User) {
                $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
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
    /**
     * DB app_settings column → config key. Both current overrides are integer
     * settings, so applySettingOverrides() int-casts every value. (The finance-only
     * app only exposes these two file limits to the admin UI.)
     *
     * @var array<string, string>
     */
    public const SETTING_OVERRIDES = [
        'files_max_upload_mb' => 'files.max_upload_mb',
        'files_blob_orphan_grace_hours' => 'files.blob_orphan_grace_hours',
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

        foreach (self::SETTING_OVERRIDES as $col => $cfg) {
            if (! isset($values[$col])) {
                continue;
            }
            $v = $values[$col];
            config([$cfg => is_numeric($v) ? (int) $v : 0]);
        }
    }

    /**
     * Bridge the admin-configured DB SMTP settings onto Laravel's mailer so
     * Fortify's password-reset / email-verification notifications send through the
     * SAME SMTP the Notifications settings page configures (ChannelNotifier uses a
     * raw transport; config/mail defaults to `log`). Build/no-DB safe.
     */
    private function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }
            $s = AppSettings::current();
            if (! $s->mail_enabled || ! filled($s->smtp_host)) {
                return;
            }
            // Egress-guard the SMTP host before wiring it into the mailer that
            // Fortify's password-reset / email-verification notifications use
            // (that path goes through the Mail facade, bypassing ChannelNotifier's
            // send-time guard). Refuse the cloud-metadata surface / link-local /
            // hardened-blocked ranges even if such a host was persisted before the
            // guard existed. Fails closed: keep the config default (`log`) mailer
            // rather than connecting to a disallowed host. Mirrors
            // ChannelNotifier::mailTo() and NotificationsController's SafeHost.
            if (! OutboundUrl::hostAllowed((string) $s->smtp_host)) {
                return;
            }
            $str = static fn (mixed $v, string $default = ''): string => is_scalar($v) ? (string) $v : $default;
            $scheme = $str($s->smtp_encryption, 'tls') === 'ssl' ? 'smtps' : 'smtp';
            $appHost = parse_url($str(config('app.url')), PHP_URL_HOST);
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => $scheme,
                'mail.mailers.smtp.host' => $str($s->smtp_host),
                'mail.mailers.smtp.port' => $s->smtp_port ?: ($scheme === 'smtps' ? 465 : 587),
                'mail.mailers.smtp.username' => filled($s->smtp_username) ? $str($s->smtp_username) : null,
                'mail.mailers.smtp.password' => filled($s->smtp_password) ? $str($s->smtp_password) : null,
                'mail.from.address' => filled($s->smtp_from_address) ? $str($s->smtp_from_address) : ('no-reply@'.(is_string($appHost) ? $appHost : 'localhost')),
                'mail.from.name' => filled($s->smtp_from_name) ? $str($s->smtp_from_name) : $str(config('app.name'), 'Ledgerline'),
            ]);
        } catch (\Throwable) {
            // build / no-DB: keep config defaults.
        }
    }
}
