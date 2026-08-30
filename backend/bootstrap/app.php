<?php

use App\Http\Middleware\BearerFromQuery;
use App\Http\Middleware\BlockGuard;
use App\Http\Middleware\EnsureModule;
use App\Http\Middleware\LogRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Services\Ops\ErrorRecorder;
use App\Support\ApiAccessTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Finance module Artisan commands (e.g. `finance:run-recurring-invoices`)
    // live beside the rest of the module under Infrastructure/Scheduling,
    // not app/Console/Commands, so they need an explicit discovery path.
    ->withCommands([app_path('Modules/Finance/Infrastructure/Scheduling')])
    ->withMiddleware(function (Middleware $middleware): void {
        // BlockGuard (early, before controllers) refuses blocked IPs/users on
        // every request; LogRequest (terminable) records the full request trail
        // after the response. Applied to both web + api.
        // BlockGuard runs twice on web: prepend (early — IP + any Sanctum bearer)
        // and again appended AFTER StartSession, where $request->user() resolves the
        // web-session user — so a blocked account holding a live cookie session
        // (Redis-backed, not evicted by a DB sessions delete) is refused 403 on its
        // very next web request, driver-agnostically. The repeat IP check is cached.
        $middleware->web(prepend: [BlockGuard::class], append: [BlockGuard::class, SetLocale::class, SecurityHeaders::class, LogRequest::class]);
        // WebDAV authenticates via HTTP Basic (Sabre) and uses non-form verbs
        // (PUT/DELETE/PROPFIND/MKCOL/…) — exempt it from session CSRF.
        $middleware->validateCsrfTokens(except: ['dav', 'dav/*']);
        // Security headers on the token API too (nosniff / referrer / permissions /
        // HSTS). Sensitive-byte routes set their own sandbox CSP, which the
        // middleware preserves.
        $middleware->api(prepend: [BlockGuard::class, BearerFromQuery::class], append: [SecurityHeaders::class, LogRequest::class]);

        // The SPA authenticates with a bearer TOKEN (not a session cookie), so the
        // API stays stateless — no statefulApi()/EnsureFrontendRequestsAreStateful
        // (which would apply session + CSRF to same-origin /api calls and 419 the
        // token login). This keeps the API portable to a future non-Laravel host.

        // Sanctum ability guards for the token-scoped mobile/CLI API.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'module' => EnsureModule::class,
            // Rate limiting is ENABLED (internet-facing via the NetBird proxy at
            // https://home.pinlo.me, 2026-08-09). The `throttle` alias points at the
            // framework limiter; inline `throttle:N,1` route limits apply, and the
            // named limiters (login/two-factor/fortify/auth-pair/dav/share-unlock/
            // invite) are defined in App\Support\RateLimiters, registered per-request-
            // safely by App\Providers\RateLimitServiceProvider (booted() after the
            // deferred CacheServiceProvider is warm — see the boot-churn note there).
            'throttle' => ThrottleRequests::class,
        ]);

        // Behind a TLS-terminating reverse proxy, honour X-Forwarded-* so
        // Laravel sees the real HTTPS scheme (and thus emits Secure cookies /
        // HTTPS URLs). Configure via TRUSTED_PROXIES as a comma-separated list of
        // proxy IPs/CIDRs; defaults to trusting none. Trusting '*' is intentionally
        // NOT supported — it lets any client forge X-Forwarded-For and rotate a
        // fresh bucket per request, defeating the IP-keyed login/2FA/pair limiters
        // (OWASP / RFC 7239: never blindly trust all proxies). A literal '*' entry
        // is ignored (falls back to trusting none).
        $proxies = array_filter(
            array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
            static fn (string $p): bool => $p !== '' && $p !== '*',
        );
        if ($proxies !== []) {
            $middleware->trustProxies(
                at: array_values($proxies),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Mirror unhandled exceptions into the in-app error log (no external
        // service). Recording is best-effort and must never affect the request.
        $exceptions->report(function (Throwable $e): void {
            app(ErrorRecorder::class)->record($e);
        });

        // Audit a rejected API request (throttled, with a reason code) so a device
        // that keeps getting 401/403 is diagnosable. Only fires when a bearer was
        // presented; falls through to normal rendering (returns nothing).
        $exceptions->render(function (Throwable $e, Request $request): void {
            if (! $request->is('api/*')) {
                return;
            }
            if ($e instanceof AuthenticationException) {
                ApiAccessTrail::unauthorized($request, 401);
            } elseif ($e instanceof MissingAbilityException || $e instanceof AuthorizationException) {
                ApiAccessTrail::unauthorized($request, 403);
            }
        });
    })->create();
