<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AvatarFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Manager\OAuth2\User as OAuth2User;
use Throwable;

/**
 * Handles the Pocket-ID OIDC authentication flow.
 *
 * Pocket-ID is the sole identity provider. The application never sees user
 * credentials; it only receives the provider's signed userinfo response and
 * matches (or provisions) a local account on the stable subject identifier.
 */
class PocketIdController extends Controller
{
    /**
     * Redirect the user to Pocket-ID to begin the authorization-code flow.
     *
     * The flow is stateful (an anti-CSRF "state" value is stored in the
     * session) and PKCE-protected, as configured in config/services.php.
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Carry the "public / shared computer" choice through the OIDC round-trip
        // in the session, so the callback can decline the long-lived remember-me
        // cookie (mirrors the vault's own public-computer control).
        $request->session()->put('oidc_public_computer', $request->boolean('public'));

        // pocketid is an OAuth2 driver, so the resolved provider is the concrete
        // Two\AbstractProvider (which exposes scopes() and an Illuminate redirect).
        $driver = Socialite::driver('pocketid');
        abort_unless($driver instanceof AbstractProvider, 500);

        return $driver->scopes(['openid', 'profile', 'email', 'groups'])->redirect();
    }

    /**
     * Handle the callback from Pocket-ID and sign the user in.
     */
    public function callback(Request $request, AvatarFetcher $avatars): RedirectResponse
    {
        try {
            $oidcUser = Socialite::driver('pocketid')->user();
        } catch (Throwable) {
            // Covers invalid/expired state, denied consent or token errors.
            AuditLog::record('auth.login_failed', null, ['reason' => 'token_or_state'], null);

            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        // pocketid is an OAuth2 driver, so the resolved user is always the concrete
        // Two\User (which exposes getRaw()); guard defensively rather than trust the
        // narrower Contracts\User interface.
        if (! $oidcUser instanceof SocialiteUser) {
            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        $sub = (string) $oidcUser->getId();
        $raw = $oidcUser->getRaw();

        // Only trust the e-mail address once the provider has verified it; an
        // unverified address must never be persisted or used for matching.
        $emailVerified = ($raw['email_verified'] ?? false) === true;
        $email = $emailVerified ? $oidcUser->getEmail() : null;

        if (! $this->mayProvision($sub, $email)) {
            // Deliberately generic: never reveal whether the account exists or
            // why a subject was rejected.
            AuditLog::record('auth.login_denied', null, ['sub' => $sub], null);

            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        // Group memberships from the OIDC `groups` claim (used to gate the
        // non-personal, workspace-wide settings). Refreshed on every sign-in.
        $groups = array_values(array_filter(array_map(
            static fn (mixed $g): string => is_scalar($g) ? (string) $g : '',
            is_array($raw['groups'] ?? null) ? $raw['groups'] : [],
        )));

        try {
            $user = User::updateOrCreate(
                ['oidc_sub' => $sub],
                [
                    'name' => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Unknown',
                    'email' => $email,
                    'email_verified_at' => $emailVerified ? now() : null,
                ],
            );

            // Record who first claimed this install so ownership survives the
            // owner self-deleting (see mayProvision). No-op once a claim exists.
            $this->recordInstallClaim($sub);

            // Remember the current avatar source so it can be refreshed later, and
            // download it once on first sign-in (or if the stored image went away).
            $url = $oidcUser->getAvatar() ?: ($oidcUser->getRaw()['picture'] ?? null);
            if (is_string($url) && $url !== '' && $user->avatar_url !== $url) {
                $user->update(['avatar_url' => $url]);
            }
            if (empty($user->avatar)) {
                $avatars->fetch($user, $user->avatar_url);
            }

            // `groups` is not mass-assignable — set it server-side from the OIDC claim.
            $user->forceFill(['groups' => $groups, 'last_login_at' => now()])->save();
        } catch (Throwable) {
            // A UNIQUE clash (e.g. two authorized subjects sharing one verified
            // e-mail — a QueryException, itself a Throwable) or any other
            // persistence error must not surface as a 500.
            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        // On a public/shared computer, don't issue the persistent remember-me
        // cookie and let the session cookie die when the browser closes.
        $public = (bool) $request->session()->pull('oidc_public_computer', false);
        if ($public) {
            config(['session.expire_on_close' => true]);
        }
        Auth::login($user, remember: ! $public);
        AuditLog::record('auth.login', $user, ['public_computer' => $public], $user->id);

        // Keep the provider id_token so logout can end the SSO session too. The
        // pocketid driver returns the SocialiteProviders manager user, which
        // carries the raw token response body.
        if ($oidcUser instanceof OAuth2User) {
            $tokenResponse = $oidcUser->accessTokenResponseBody;
            $idToken = is_array($tokenResponse) ? ($tokenResponse['id_token'] ?? null) : null;
            if (is_string($idToken)) {
                $request->session()->put('oidc_id_token', $idToken);
            }
        }

        // Prevent session fixation by issuing a fresh session identifier.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Decide whether the given OIDC subject is allowed to sign in.
     *
     * Single-tenant policy: an explicit allow-list (subjects and/or verified
     * e-mails) wins when configured; otherwise the first identity to sign in
     * claims the sole account and every later subject is rejected.
     */
    private function mayProvision(string $sub, ?string $email): bool
    {
        $allowedSubs = (array) config('services.pocketid.allowed_subs', []);
        $allowedEmails = (array) config('services.pocketid.allowed_emails', []);

        if ($allowedSubs !== [] || $allowedEmails !== []) {
            return in_array($sub, $allowedSubs, true)
                || ($email !== null && in_array($email, $allowedEmails, true));
        }

        // No allow-list: first user wins. A provisioned account only accepts its
        // own subject.
        $existing = User::query()->first();
        if ($existing !== null) {
            return $existing->oidc_sub === $sub;
        }

        // No live users. This is either a brand-new install or one whose sole
        // owner self-deleted. A persisted install claim (which outlives user
        // deletion) records the original owner: after an erasure, only that
        // subject may re-provision; a brand-new subject is rejected. If no claim
        // exists yet, this is a genuine fresh install and the first caller wins.
        $claim = DB::table('install_claims')->value('oidc_sub');

        return $claim === null || $claim === $sub;
    }

    /**
     * Persist the OIDC subject that first claimed this single-tenant install.
     *
     * Idempotent: only the first successful provision writes a row; every later
     * call is a no-op. The row deliberately survives user deletion so ownership
     * cannot be silently reassigned after the owner erases their account.
     */
    private function recordInstallClaim(string $sub): void
    {
        if (DB::table('install_claims')->exists()) {
            return;
        }

        DB::table('install_claims')->insert([
            'oidc_sub' => $sub,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log the user out and invalidate the local session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $idToken = $request->session()->get('oidc_id_token');

        $actorId = Auth::id();
        Auth::logout();
        AuditLog::record('auth.logout', null, [], $actorId !== null ? (int) $actorId : null);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // RP-initiated logout: end the Pocket-ID SSO session too, otherwise a
        // fresh sign-in silently re-authenticates. Falls back to the local login
        // page when no end-session endpoint is configured.
        $endSession = config('services.pocketid.logout_endpoint');
        if (is_string($endSession) && $endSession !== '') {
            $params = ['post_logout_redirect_uri' => route('login')];
            if (is_string($idToken) && $idToken !== '') {
                $params['id_token_hint'] = $idToken;
            }

            return redirect()->away($endSession.'?'.http_build_query($params));
        }

        return redirect()->route('login');
    }
}
