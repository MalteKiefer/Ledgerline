<?php

use App\Http\Controllers\DavController;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // CardDAV: mounted outside the web group (no session/CSRF); sabre does
        // its own Basic auth. .well-known enables client auto-discovery.
        then: function (): void {
            Route::any('dav/{path?}', [DavController::class, 'handle'])->where('path', '.*');
            Route::redirect('.well-known/carddav', '/dav/', 301);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);

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
    })->create();
