<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OutboundUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pull EUR foreign-exchange rates once a day and cache them for the fuzzy receipt↔booking
 * amount suggestions (Finance). The request sends NO user data — it only GETs public rates
 * from the configured host (frankfurter.app / ECB by default) — and passes the SSRF guard.
 * Best-effort: on any failure the cached (or config-default) rates keep being used.
 */
final class FetchExchangeRates extends Command
{
    protected $signature = 'finance:fetch-fx';

    protected $description = 'Fetch daily EUR exchange rates for finance amount suggestions';

    public const CACHE_KEY = 'finance.fx_to_eur';

    public function handle(): int
    {
        $url = config('finance.fx_url');
        if (! is_string($url) || $url === '' || ! OutboundUrl::safe($url)) {
            $this->warn('FX URL missing or blocked by the SSRF guard; keeping cached rates.');

            return self::SUCCESS;
        }

        $symbolsRaw = config('finance.fx_symbols', []);
        $symbols = array_values(array_filter(is_array($symbolsRaw) ? $symbolsRaw : [], 'is_string'));

        try {
            $response = OutboundUrl::client($url, 8)
                ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)'])
                ->get($url, ['from' => 'EUR', 'to' => implode(',', $symbols)]);

            if (! $response->successful()) {
                $this->warn("FX fetch failed (HTTP {$response->status()}); keeping cached rates.");

                return self::SUCCESS;
            }

            $json = $response->json();
            $rates = is_array($json) && isset($json['rates']) && is_array($json['rates']) ? $json['rates'] : [];

            // Upstream gives EUR→X; store the inverse X→EUR that the client needs.
            $toEur = ['EUR' => 1.0];
            foreach ($rates as $cur => $rate) {
                if (is_string($cur) && is_numeric($rate) && (float) $rate > 0) {
                    $toEur[strtoupper($cur)] = round(1 / (float) $rate, 6);
                }
            }

            if (count($toEur) < 2) {
                $this->warn('FX response had no usable rates; keeping cached rates.');

                return self::SUCCESS;
            }

            Cache::forever(self::CACHE_KEY, [
                'rates' => $toEur,
                'fetched_at' => now()->toIso8601String(),
            ]);

            $this->info('Fetched '.count($toEur).' EUR rates.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->warn('FX fetch errored; keeping cached rates.');

            return self::SUCCESS;
        }
    }
}
