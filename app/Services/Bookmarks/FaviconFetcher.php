<?php

declare(strict_types=1);

namespace App\Services\Bookmarks;

use App\Support\BlobStore;
use App\Support\OutboundUrl;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fetches and caches site favicons server-side (no third-party favicon
 * service, no browser hitting foreign hosts). Icons live on the files disk
 * under favicons/<sha1(host)>; hosts without one are negative-cached.
 */
class FaviconFetcher
{
    private const MAX_BYTES = 128 * 1024;

    /** @return array{path: string, mime: string}|null a stored icon for the host */
    public function fetch(string $host): ?array
    {
        $host = strtolower(trim($host));
        if ($host === '' || strlen($host) > 253 || preg_match('/^[a-z0-9.\-]+$/', $host) !== 1) {
            return null;
        }

        $key = 'favicon:'.$host;
        $cached = Cache::get($key);
        if ($cached === 'none') {
            return null;
        }
        $disk = BlobStore::disk();
        if (is_array($cached) && $disk->exists($cached['path'])) {
            return $cached;
        }

        $stored = $this->download($host, $disk);
        Cache::put($key, $stored ?? 'none', now()->addDays(7));

        return $stored;
    }

    /** @return array{path: string, mime: string}|null */
    private function download(string $host, $disk): ?array
    {
        foreach (["https://{$host}/favicon.ico", $this->declaredIcon($host)] as $url) {
            if ($url === null || ! OutboundUrl::safe($url)) {
                continue;
            }
            try {
                $res = OutboundUrl::client($url, 5)->get($url);
                $body = $res->body();
                $mime = strtolower(strtok((string) $res->header('Content-Type'), ';') ?: '');
                if (! $res->successful() || $body === '' || strlen($body) > self::MAX_BYTES) {
                    continue;
                }
                if (! str_starts_with($mime, 'image/') && $mime !== 'application/octet-stream') {
                    continue;
                }
                $path = 'favicons/'.sha1($host);
                $disk->put($path, $body);

                return ['path' => $path, 'mime' => str_starts_with($mime, 'image/') ? $mime : 'image/x-icon'];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /** The icon the site's HTML declares (<link rel="icon" …>), if any. */
    private function declaredIcon(string $host): ?string
    {
        $page = "https://{$host}/";
        if (! OutboundUrl::safe($page)) {
            return null;
        }
        try {
            $html = substr(OutboundUrl::client($page, 5)->get($page)->body(), 0, 200_000);
        } catch (Throwable) {
            return null;
        }
        if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]*>/i', $html, $tag) !== 1
            || preg_match('/href=["\']([^"\']+)["\']/i', $tag[0], $href) !== 1) {
            return null;
        }
        $icon = html_entity_decode($href[1]);
        if (str_starts_with($icon, 'data:')) {
            return null;
        }

        // Resolve relative hrefs against the site root.
        return str_starts_with($icon, 'http') ? $icon : "https://{$host}/".ltrim($icon, '/');
    }
}
