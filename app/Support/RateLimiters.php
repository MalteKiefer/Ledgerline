<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Named HTTP rate limiters for the whole app.
 *
 * Ledgerline is served on the public internet (via the NetBird reverse proxy at
 * https://home.pinlo.me), so anti-automation is load-bearing: without these the
 * password factor AND the 6-digit TOTP second factor are online-brute-forceable.
 * Every `throttle:<name>` route/config declaration resolves one of the limiters
 * defined here.
 *
 * Registration order matters — see RateLimitServiceProvider: these are defined
 * inside an $app->booted() callback AFTER the deferred CacheServiceProvider (which
 * owns both `cache` and the Illuminate\Cache\RateLimiter singleton) has been fully
 * resolved, so they land on the final RateLimiter instance and are not discarded.
 * All limiters key on the real client IP; TRUSTED_PROXIES must scope X-Forwarded-For
 * to the proxy only (bootstrap/app.php) so the IP bucket cannot be spoofed.
 */
final class RateLimiters
{
    public static function register(): void
    {
        // Password factor: 5 attempts/min per (email + IP). Blocks credential
        // stuffing / dictionary runs against the owner's login.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = $request->string(Fortify::username())->lower()->value();

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.(string) $request->ip()));
        });

        // Second factor: 5 TOTP submissions/min per pending-login (or IP fallback).
        // Without this the 10^6 6-digit space is online-guessable in hours — this
        // is what actually keeps 2FA a real second factor on an internet-facing box.
        RateLimiter::for('two-factor', function (Request $request): Limit {
            $id = $request->session()->get('login.id');

            return Limit::perMinute(5)->by(is_scalar($id) ? (string) $id : (string) $request->ip());
        });

        // Blanket per-IP cap on the whole Fortify auth route group (register /
        // forgot-password / reset-password / verification-notification). Stacks
        // with the tighter login/two-factor buckets; stops reset-mail bombing and
        // account enumeration on the otherwise-unthrottled public auth POSTs.
        RateLimiter::for('fortify', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->ip()));

        // Public QR device-pairing exchange — the one-time code is the only
        // credential, so cap it hard (a real user pairs a handful of devices).
        RateLimiter::for('auth-pair', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        // Unauthenticated credential-guess gates (public link / invite): each
        // guesses an Argon2id-hashed secret, so keep them tight per IP.
        RateLimiter::for('share-unlock', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('invite', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        // WebDAV: every failed HTTP-Basic attempt runs an Argon2id verify and
        // clients resend on each request — cap per IP to bound brute-force + CPU DoS.
        RateLimiter::for('dav', fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->ip()));
    }
}
