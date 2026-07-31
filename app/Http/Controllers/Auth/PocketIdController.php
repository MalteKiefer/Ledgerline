<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

/**
 * Optional Pocket-ID (OIDC / OAuth2) sign-in.
 *
 * This is an ADDITIONAL login option that coexists with the first-party Fortify
 * auth (email + password + optional TOTP). It is fully disabled when its
 * POCKETID_* env vars are unset: the redirect route bounces back to the login
 * page and the login view hides the button.
 *
 * The application never sees the user's provider credentials; it only receives
 * the provider's signed userinfo response and matches (or provisions) a local
 * account on the stable subject identifier. The privilege `role` is NEVER
 * derived from OIDC claims — a provisioned account is always a plain `user`.
 */
class PocketIdController extends Controller
{
    /**
     * Redirect the user to Pocket-ID to begin the authorization-code flow.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_unavailable'),
            ]);
        }

        // Carry the "public / shared computer" choice through the OIDC round-trip
        // so the callback can decline the long-lived remember-me cookie.
        $request->session()->put('oidc_public_computer', $request->boolean('public'));

        // pocketid is an OAuth2 driver, so the resolved provider is the concrete
        // Two\AbstractProvider (which exposes scopes() and an Illuminate redirect).
        $driver = Socialite::driver('pocketid');
        abort_unless($driver instanceof AbstractProvider, 500);

        return $driver->scopes(['openid', 'profile', 'email'])->redirect();
    }

    /**
     * Handle the callback from Pocket-ID and sign the user in.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_unavailable'),
            ]);
        }

        try {
            $oidcUser = Socialite::driver('pocketid')->user();
        } catch (Throwable) {
            // Covers invalid/expired state, denied consent or token errors.
            AuditLog::record('auth.login_failed', null, ['provider' => 'pocketid', 'reason' => 'token_or_state'], null);

            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_failed'),
            ]);
        }

        // pocketid is an OAuth2 driver, so the resolved user is always the concrete
        // Two\User; guard defensively rather than trust the narrower contract.
        if (! $oidcUser instanceof SocialiteUser) {
            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_failed'),
            ]);
        }

        $sub = (string) $oidcUser->getId();
        if ($sub === '') {
            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_failed'),
            ]);
        }

        $raw = $oidcUser->getRaw();

        // Only trust the e-mail once the provider has verified it; an unverified
        // address must never be persisted or used for matching.
        $emailVerified = ($raw['email_verified'] ?? false) === true;
        $rawEmail = $oidcUser->getEmail();
        $email = ($emailVerified && is_string($rawEmail) && $rawEmail !== '') ? $rawEmail : null;

        try {
            // Match on the stable subject first, then fall back to a verified email
            // (binds an existing first-party account to this OIDC subject on first
            // OIDC sign-in). Otherwise provision a fresh account.
            $user = User::query()->where('oidc_sub', $sub)->first();
            if ($user === null && $email !== null) {
                $user = User::query()->where('email', $email)->first();
            }

            if ($user === null) {
                // A brand-new account can only be provisioned with a verified email
                // (it is the account's login identity and unique key).
                if ($email === null) {
                    AuditLog::record('auth.login_denied', null, ['provider' => 'pocketid', 'reason' => 'no_verified_email'], null);

                    return redirect()->route('login')->withErrors([
                        'pocketid' => __('auth_ui.pocketid_failed'),
                    ]);
                }

                $name = $oidcUser->getName() ?: ($oidcUser->getNickname() ?: 'User');
                $user = new User;
                // `role` is the privilege boundary and NEVER comes from OIDC claims:
                // a provisioned account is always a plain user (forceFill — role is
                // not mass-assignable).
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'role' => 'user',
                    'email_verified_at' => now(),
                ]);
            }

            // Bind (or re-affirm) the OIDC subject on the account. `oidc_sub` is an
            // identity binding, not user-settable — set it server-side via forceFill.
            $user->forceFill(['oidc_sub' => $sub]);
            $user->save();
        } catch (Throwable) {
            // A UNIQUE clash or any other persistence error must not surface as a 500.
            return redirect()->route('login')->withErrors([
                'pocketid' => __('auth_ui.pocketid_failed'),
            ]);
        }

        // On a public/shared computer, don't issue the persistent remember-me
        // cookie and let the session cookie die when the browser closes.
        $public = (bool) $request->session()->pull('oidc_public_computer', false);
        if ($public) {
            config(['session.expire_on_close' => true]);
        }

        Auth::login($user, remember: ! $public);
        AuditLog::record('auth.login', $user, ['provider' => 'pocketid', 'public_computer' => $public], $user->id);

        // Prevent session fixation by issuing a fresh session identifier (mirrors
        // Fortify's own login handling).
        $request->session()->regenerate();

        return redirect()->intended(route('finance.index'));
    }

    /**
     * Whether Pocket-ID sign-in is configured and safe to use.
     *
     * Requires the client id, client secret and a valid HTTPS base URL with a
     * host — the base URL is operator config, but validating it here keeps a
     * misconfiguration from producing an SSRF/open-redirect target and gates the
     * whole feature off when the env vars are unset.
     */
    public static function configured(): bool
    {
        $clientId = config('services.pocketid.client_id');
        $secret = config('services.pocketid.client_secret');
        $baseUrl = config('services.pocketid.base_url');

        if (! is_string($clientId) || $clientId === '') {
            return false;
        }
        if (! is_string($secret) || $secret === '') {
            return false;
        }
        if (! is_string($baseUrl) || $baseUrl === '') {
            return false;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        return $scheme === 'https' && is_string($host) && $host !== '';
    }
}
