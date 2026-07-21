<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\BlobStore;
use App\Support\OutboundUrl;
use Throwable;

/**
 * Downloads a user's Pocket-ID avatar and stores it on the object-storage disk,
 * so it is served same-origin from our domain (Pocket-ID hotlink-blocks it
 * cross-origin) and no third-party request happens at render time.
 *
 * Used both at first sign-in and by the manual "refresh avatar" action.
 */
class AvatarFetcher
{
    /**
     * Fetch $url into storage and point the user's avatar at it. Returns whether
     * an image was stored. All failures are swallowed (avatar is non-essential).
     */
    public function fetch(User $user, ?string $url): bool
    {
        if (! is_string($url) || $url === '' || ! $this->hostAllowed($url)) {
            return false;
        }

        try {
            // OutboundUrl pins the resolved IP (DNS-rebind safe) and refuses
            // metadata/link-local addresses; it also disables redirect following
            // (a redirect could escape the allowed host). The host is still
            // pinned to Pocket-ID by hostAllowed() above.
            $response = OutboundUrl::client($url, 5)->get($url);

            if (! $response->successful()) {
                return false;
            }

            $type = (string) $response->header('Content-Type');
            if (! str_starts_with($type, 'image/')) {
                return false;
            }

            $body = (string) $response->body();

            // Reject anything implausibly large for an avatar (5 MiB cap).
            if ($body === '' || strlen($body) > 5 * 1024 * 1024) {
                return false;
            }

            $extension = match (true) {
                str_contains($type, 'png') => 'png',
                str_contains($type, 'webp') => 'webp',
                str_contains($type, 'gif') => 'gif',
                default => 'jpg',
            };

            $disk = BlobStore::disk();
            $path = "avatars/{$user->id}.{$extension}";
            $disk->put($path, $body);

            // Drop a previous avatar with a different extension.
            if (is_string($user->avatar) && $user->avatar !== '' && $user->avatar !== $path) {
                $disk->delete($user->avatar);
            }

            $user->update(['avatar' => $path, 'avatar_url' => $url]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * SSRF guard: only ever fetch over http(s) from the configured Pocket-ID
     * host, so the (semi-trusted) "picture" claim cannot point the server at
     * internal/loopback addresses or other hosts.
     */
    private function hostAllowed(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $baseUrl = config('services.pocketid.base_url');
        $allowedHost = is_string($baseUrl) ? parse_url($baseUrl, PHP_URL_HOST) : null;

        return in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && is_string($allowedHost)
            && strcasecmp($host, $allowedHost) === 0;
    }
}
