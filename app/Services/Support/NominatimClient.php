<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Support\OutboundUrl;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Shared, rate-limited client for OpenStreetMap's Nominatim service. Serialises
 * requests across all workers so the one-per-second usage policy is honoured
 * during bulk imports as well as interactive lookups. Nothing location-bearing
 * is cached here — only a request timestamp for the throttle window.
 *
 * NOTE — this is a DELIBERATE third-party egress: a user-initiated place/address
 * lookup (or a photo's GPS) is sent to nominatim.openstreetmap.org, so it leaves
 * the zero-knowledge boundary. It is never automatic on upload. Requests go
 * through the SSRF guard like every other outbound call; self-host Nominatim or
 * Photon to keep the lookup in-boundary.
 */
class NominatimClient
{
    private const BASE = 'https://nominatim.openstreetmap.org';

    /**
     * Perform a throttled Nominatim request through the SSRF-guarded client,
     * returning the decoded JSON body or null on any failure.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    public function get(string $endpoint, array $query): ?array
    {
        try {
            $this->throttle();

            $response = OutboundUrl::client(self::BASE, 5)
                ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)'])
                ->get(self::BASE.'/'.ltrim($endpoint, '/'), $query);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Space requests across all workers so Nominatim's one-per-second policy is
     * respected. A short lock serialises workers; the stored timestamp enforces
     * the interval.
     */
    private function throttle(): void
    {
        $interval = (int) config('gallery.geocode_interval_ms', 1100);
        if ($interval <= 0) {
            return;
        }

        $lock = Cache::lock('geocode:nominatim:lock', 15);

        try {
            $lock->block(30);

            $last = (float) Cache::get('geocode:nominatim:last', 0.0);
            $waitMs = $interval - (int) ((microtime(true) - $last) * 1000);
            if ($waitMs > 0 && $waitMs <= $interval) {
                usleep($waitMs * 1000);
            }

            Cache::put('geocode:nominatim:last', microtime(true), now()->addMinutes(5));
        } catch (Throwable) {
            // Could not acquire the lock in time; proceed without spacing rather
            // than fail the whole lookup.
        } finally {
            optional($lock)->release();
        }
    }
}
