<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Wires Laravel Fortify (first-party auth) to our own iOS-styled Blade views and
 * rate limiters. Fortify owns the security-critical flows (login, password reset,
 * email verification, TOTP two-factor + recovery codes); we only supply views,
 * action customisations and limiters. Login is independent of the ZK vault
 * passphrase — it authenticates the session, nothing more.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Views (all under resources/views/auth, iOS-styled).
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Self-registration is gated by a workspace toggle (an admin creates users
        // by default). When off, the register page redirects to login; the POST is
        // also blocked in CreateNewUser (defence in depth).
        Fortify::registerView(fn () => self::registrationOpen() ? view('auth.register') : redirect()->route('login'));

        // No rate limiters: throttling is disabled app-wide (private 2-user home
        // LAN, not internet-facing) — the `throttle` alias is a no-op. Fortify's
        // login/two-factor throttle groups therefore pass through inert. See the
        // Security register (2026-08-08).
    }

    /** Whether self-service registration is currently enabled workspace-wide. */
    public static function registrationOpen(): bool
    {
        return (bool) AppSettings::current()->allow_registration;
    }
}
