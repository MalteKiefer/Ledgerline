<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUser;
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
        return Socialite::driver('pocketid')->redirect();
    }

    /**
     * Handle the callback from Pocket-ID and sign the user in.
     */
    public function callback(Request $request): RedirectResponse
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

        $this->syncAvatar($user, $oidcUser);

        Auth::login($user, remember: true);

        // Prevent session fixation by issuing a fresh session identifier.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Download the user's Pocket-ID avatar and store it on the local disk.
     *
     * The image is hotlink-blocked cross-origin and would otherwise be an
     * external runtime request, so we fetch it once at login and serve it from
     * our own domain. The avatar is non-essential: any failure is swallowed so
     * it can never block sign-in. The stored value is a relative path on the
     * private "local" disk, served through the authenticated avatar route.
     */
    private function syncAvatar(User $user, SocialiteUser $oidcUser): void
    {
        $url = $oidcUser->getAvatar() ?: ($oidcUser->getRaw()['picture'] ?? null);

        if (! is_string($url) || $url === '') {
            return;
        }

        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return;
            }

            $type = (string) $response->header('Content-Type');
            $extension = match (true) {
                str_contains($type, 'png') => 'png',
                str_contains($type, 'webp') => 'webp',
                str_contains($type, 'gif') => 'gif',
                default => 'jpg',
            };

            $path = "avatars/{$user->id}.{$extension}";
            Storage::disk('local')->put($path, $response->body());

            $user->update(['avatar' => $path]);
        } catch (Throwable) {
            // Avatar is optional; never fail login because of it.
        }
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
