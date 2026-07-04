<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = User::updateOrCreate(
            ['oidc_sub' => $sub],
            [
                'name' => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Unknown',
                'email' => $email,
                'email_verified_at' => $emailVerified ? now() : null,
                'groups' => $groups,
            ],
        );

        // Remember the current avatar source so it can be refreshed later, and
        // download it once on first sign-in (or if the stored image went away).
        $url = $oidcUser->getAvatar() ?: ($oidcUser->getRaw()['picture'] ?? null);
        if (is_string($url) && $url !== '' && $user->avatar_url !== $url) {
            $user->update(['avatar_url' => $url]);
        }
        if (empty($user->avatar)) {
            $avatars->fetch($user, $user->avatar_url);
        }

        Auth::login($user, remember: true);

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
        // own subject; a brand-new install accepts the first caller.
        $existing = User::query()->first();

        return $existing === null || $existing->oidc_sub === $sub;
    }

    /**
     * Log the user out and invalidate the local session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
