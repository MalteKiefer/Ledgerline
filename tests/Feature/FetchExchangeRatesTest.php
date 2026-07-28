<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FetchExchangeRates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FetchExchangeRatesTest extends TestCase
{
    public function test_it_fetches_and_inverts_eur_rates_into_the_cache(): void
    {
        config(['finance.fx_url' => 'https://fx.test/latest']);
        Http::fake(['*' => Http::response(['base' => 'EUR', 'rates' => ['USD' => 1.08, 'GBP' => 0.85]], 200)]);

        $this->artisan('finance:fetch-fx')->assertSuccessful();

        $cached = Cache::get(FetchExchangeRates::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertSame(1.0, $cached['rates']['EUR']);
        $this->assertSame(round(1 / 1.08, 6), $cached['rates']['USD']); // EUR→X inverted to X→EUR
        $this->assertArrayHasKey('fetched_at', $cached);
    }

    public function test_it_keeps_cached_rates_on_a_failed_fetch(): void
    {
        Cache::forever(FetchExchangeRates::CACHE_KEY, ['rates' => ['EUR' => 1.0, 'USD' => 0.9], 'fetched_at' => 'x']);
        config(['finance.fx_url' => 'https://fx.test/latest']);
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->artisan('finance:fetch-fx')->assertSuccessful();

        $this->assertSame(0.9, Cache::get(FetchExchangeRates::CACHE_KEY)['rates']['USD']);
    }
}
