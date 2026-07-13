<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
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
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('pocketid')
            ->scopes(['openid', 'profile', 'email', 'groups'])
            ->redirect();
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
            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        // Group memberships from the OIDC `groups` claim (used to gate the
        // non-personal, workspace-wide settings). Refreshed on every sign-in.
        $groups = array_values(array_filter(array_map(
            'strval',
            is_array($raw['groups'] ?? null) ? $raw['groups'] : [],
        )));

        try {
            $user = User::updateOrCreate(
                ['oidc_sub' => $sub],
                [
                    'name' => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Unknown',
                    'email' => $email,
                    'email_verified_at' => $emailVerified ? now() : null,
                    'groups' => $groups,
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

            $user->forceFill(['last_login_at' => now()])->save();
        } catch (Throwable) {
            // A UNIQUE clash (e.g. two authorized subjects sharing one verified
            // e-mail — a QueryException, itself a Throwable) or any other
            // persistence error must not surface as a 500.
            return redirect()
                ->route('login')
                ->withErrors(['pocketid' => 'Authentication failed. Please try again.']);
        }

        Auth::login($user, remember: true);

        // Keep the provider id_token so logout can end the SSO session too.
        $idToken = $oidcUser->accessTokenResponseBody['id_token'] ?? null;
        if (is_string($idToken)) {
            $request->session()->put('oidc_id_token', $idToken);
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

        Auth::logout();
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
