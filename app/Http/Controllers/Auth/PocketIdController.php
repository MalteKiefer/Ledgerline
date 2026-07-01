<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        // Request the groups scope so we can map Pocket-ID groups to teams.
        return Socialite::driver('pocketid')
            ->scopes(['openid', 'profile', 'email', 'groups'])
            ->redirect();
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
        $this->syncTeams($user, $oidcUser);

        Auth::login($user, remember: true);

        // Prevent session fixation by issuing a fresh session identifier.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Synchronise the user's team memberships from their Pocket-ID groups.
     *
     * Each group becomes a team (key "group:<id>"). A user with no groups is
     * placed in a personal team (key "user:<id>") so they are always isolated
     * and never see another team's data.
     */
    private function syncTeams(User $user, SocialiteUser $oidcUser): void
    {
        $groups = $oidcUser->getRaw()['groups'] ?? [];
        $teamIds = [];

        if (is_array($groups)) {
            foreach ($groups as $group) {
                [$key, $name] = $this->normaliseGroup($group);

                if ($key !== null) {
                    // updateOrCreate so the humanised display name is refreshed
                    // if the group slug's presentation changes.
                    $teamIds[] = Team::updateOrCreate(['key' => $key], ['name' => $name])->id;
                }
            }
        }

        if ($teamIds === []) {
            $teamIds[] = Team::firstOrCreate(
                ['key' => 'user:'.$user->id],
                ['name' => $user->name ?: 'Personal'],
            )->id;
        }

        $user->teams()->sync($teamIds);
        $user->forgetCachedTeamIds();

        // With a single team there is nothing to choose, so activate it now.
        // Otherwise leave the active team unset so the login picker overlay
        // prompts the user to choose their default.
        if (count($teamIds) === 1) {
            session(['active_team_id' => $teamIds[0]]);
        }
    }

    /**
     * Resolve a Pocket-ID group claim entry to a [key, name] pair.
     *
     * The Pocket-ID "groups" claim carries the group slug (e.g.
     * "kiefer_networks"); the human display name is not included, so we derive
     * a readable name from the slug ("Kiefer Networks"). Objects with an
     * explicit name are also supported.
     *
     * @return array{0: string|null, 1: string}
     */
    private function normaliseGroup(mixed $group): array
    {
        if (is_string($group) && trim($group) !== '') {
            return ['group:'.Str::slug($group), Team::humanise($group)];
        }

        if (is_array($group)) {
            $id = $group['id'] ?? $group['name'] ?? null;
            $name = $group['name'] ?? $group['id'] ?? null;

            if ($id !== null) {
                return ['group:'.Str::slug((string) $id), Team::humanise((string) ($name ?? $id))];
            }
        }

        return [null, ''];
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

        // SSRF guard: only ever fetch from the configured Pocket-ID host over
        // http(s). This prevents the (semi-trusted) "picture" claim from
        // pointing the server at internal/loopback addresses or other hosts.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $allowedHost = parse_url((string) config('services.pocketid.base_url'), PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true)
            || $host === null
            || $allowedHost === null
            || strcasecmp($host, $allowedHost) !== 0) {
            return;
        }

        try {
            // Do not follow redirects (a redirect could escape the allowed host).
            $response = Http::withOptions(['allow_redirects' => false])
                ->timeout(5)
                ->get($url);

            if (! $response->successful()) {
                return;
            }

            $type = (string) $response->header('Content-Type');

            if (! str_starts_with($type, 'image/')) {
                return;
            }

            $body = (string) $response->body();

            // Reject anything implausibly large for an avatar (5 MiB cap).
            if ($body === '' || strlen($body) > 5 * 1024 * 1024) {
                return;
            }

            $extension = match (true) {
                str_contains($type, 'png') => 'png',
                str_contains($type, 'webp') => 'webp',
                str_contains($type, 'gif') => 'gif',
                default => 'jpg',
            };

            $path = "avatars/{$user->id}.{$extension}";
            Storage::disk('local')->put($path, $body);

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
