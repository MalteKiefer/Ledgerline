<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Services\Ops\ErrorRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // Sanctum ability guards for the token-scoped mobile/CLI API.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Behind a TLS-terminating reverse proxy, honour X-Forwarded-* so
        // Laravel sees the real HTTPS scheme (and thus emits Secure cookies /
        // HTTPS URLs). Configure via TRUSTED_PROXIES ('*' to trust all, or a
        // comma-separated list); defaults to trusting none.
        $proxies = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));
        if ($proxies !== []) {
            $middleware->trustProxies(
                at: in_array('*', $proxies, true) ? '*' : $proxies,
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
    })->create();
