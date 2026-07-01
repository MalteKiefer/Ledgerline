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
        $key = 'geocode:'.round($lat, 5).','.round($lon, 5);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lon): ?string {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                    ->timeout(5)
                    ->get(self::HOST, [
                        'lat' => $lat,
                        'lon' => $lon,
                        'format' => 'jsonv2',
                        'zoom' => 18,
                    ]);

                return $response->successful() ? ($response->json('display_name') ?: null) : null;
            } catch (Throwable) {
                return null;
            }
        });
    }
}
