<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Files\ReverseGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReverseGeocoderTest extends TestCase
{
    public function test_it_reverse_geocodes_via_nominatim(): void
    {
        Cache::flush();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Adalbert-Stifter-Str. 6, Neudrossenfeld'], 200),
        ]);

        $address = app(ReverseGeocoder::class)->lookup(50.05, 11.5);
        $this->assertSame('Adalbert-Stifter-Str. 6, Neudrossenfeld', $address);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'nominatim.openstreetmap.org'));
    }

    public function test_it_does_not_persist_the_resolved_place_at_rest(): void
    {
        Cache::flush();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Adalbert-Stifter-Str. 6, Neudrossenfeld'], 200),
        ]);

        app(ReverseGeocoder::class)->lookup(50.05, 11.5);

        // Under the zero-knowledge model the resolved location must never be
        // cached at rest — a second lookup therefore hits Nominatim again.
        app(ReverseGeocoder::class)->lookup(50.05, 11.5);
        Http::assertSentCount(2);
        $this->assertNull(Cache::get('geocode:50.05,11.5'));
    }

    public function test_it_returns_null_on_failure(): void
    {
        Cache::flush();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);

        $this->assertNull(app(ReverseGeocoder::class)->lookup(1.0, 2.0));
    }
}
