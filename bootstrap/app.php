<?php

use App\Http\Middleware\EnsureModule;
use App\Http\Middleware\NoThrottle;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);
        // WebDAV authenticates via HTTP Basic (Sabre) and uses non-form verbs
        // (PUT/DELETE/PROPFIND/MKCOL/…) — exempt it from session CSRF.
        $middleware->validateCsrfTokens(except: ['dav', 'dav/*']);
        // Security headers on the token API too (nosniff / referrer / permissions /
        // HSTS). Sensitive-byte routes set their own sandbox CSP, which the
        // middleware preserves.
        $middleware->api(append: [SecurityHeaders::class]);

        // Sanctum ability guards for the token-scoped mobile/CLI API.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'module' => EnsureModule::class,
            // Rate limiting is disabled app-wide (private 2-user home LAN, not
            // internet-facing) — the `throttle` alias is a no-op that ignores every
            // limit/limiter argument, so `throttle:` route declarations stay inert.
            // See the Security register (2026-08-08). Re-enable by pointing this at
            // the framework ThrottleRequests + defining named limiters again.
            'throttle' => NoThrottle::class,
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
