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
            ->scopes(['openid', 'profile', 'email'])
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

        $user = User::updateOrCreate(
            ['oidc_sub' => $oidcUser->getId()],
            [
                'name' => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Unknown',
                'email' => $oidcUser->getEmail(),
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

        // A fresh per-login token the browser binds the cached vault key to, so a
        // key cached in sessionStorage cannot survive a logout + new login (the
        // token changes every login, invalidating any stale cached key).
        $request->session()->put('vault_owner', bin2hex(random_bytes(16)));

        return redirect()->intended(route('dashboard'));
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
