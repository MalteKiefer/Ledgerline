<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Files\ReverseGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReverseGeocoderTest extends TestCase
{
    public function test_it_reverse_geocodes_via_nominatim_and_caches(): void
    {
        Cache::flush();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Adalbert-Stifter-Str. 6, Neudrossenfeld'], 200),
        ]);

        $address = new ReverseGeocoder()->lookup(50.05, 11.5);
        $this->assertSame('Adalbert-Stifter-Str. 6, Neudrossenfeld', $address);

        // Second lookup is served from cache (no second request).
        new ReverseGeocoder()->lookup(50.05, 11.5);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'nominatim.openstreetmap.org'));
    }

    public function test_it_returns_null_on_failure(): void
    {
        Cache::flush();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);

        $this->assertNull(new ReverseGeocoder()->lookup(1.0, 2.0));
    }
}
