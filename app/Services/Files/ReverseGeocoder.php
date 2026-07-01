<?php

declare(strict_types=1);

namespace App\Services\Files;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reverse-geocodes coordinates to a human-readable address via OpenStreetMap's
 * Nominatim service. Results are cached; requests only ever go to the fixed
 * Nominatim host.
 */
class ReverseGeocoder
{
    private const HOST = 'https://nominatim.openstreetmap.org/reverse';

    public function lookup(float $lat, float $lon): ?string
    {
        return $this->lookupDetailed($lat, $lon)['display'];
    }

    /**
     * Reverse-geocode to both the full display name and the structured address
     * parts (road, city, state, postcode, country, …).
     *
     * @return array{display: ?string, address: array<string, string>}
     */
    public function lookupDetailed(float $lat, float $lon): array
    {
        $key = 'geocode:'.round($lat, 5).','.round($lon, 5);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lon): array {
            try {
                $this->throttle();

                $response = Http::withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                    ->timeout(5)
                    ->get(self::HOST, [
                        'lat' => $lat,
                        'lon' => $lon,
                        'format' => 'jsonv2',
                        'zoom' => 18,
                        'addressdetails' => 1,
                    ]);

                if (! $response->successful()) {
                    return ['display' => null, 'address' => []];
                }

                return [
                    'display' => $response->json('display_name') ?: null,
                    'address' => array_map('strval', $response->json('address') ?: []),
                ];
            } catch (Throwable) {
                return ['display' => null, 'address' => []];
            }
        });
    }

    /**
     * Space requests across all workers so Nominatim's one-per-second policy is
     * respected during bulk imports. A short lock serialises workers; the stored
     * timestamp enforces the interval.
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
            // than fail the whole metadata read.
        } finally {
            optional($lock)->release();
        }
    }
}
