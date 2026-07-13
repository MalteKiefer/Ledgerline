<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Files\ReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocoderConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No coordinate blurring / rate limiting in the test so the faked URLs
        // and coordinates are deterministic.
        config(['gallery.geocode_grid_km' => 0, 'gallery.geocode_interval_ms' => 0]);
    }

    public function test_automatic_on_upload_geocoding_is_off_by_default(): void
    {
        // The privacy-safe default: no coordinate leaves the host automatically.
        $this->assertFalse((bool) config('gallery.geocode_on_upload'));
    }

    public function test_photon_is_queried_first_and_keeps_the_lookup_in_boundary(): void
    {
        config([
            'gallery.photon_url' => 'http://photon.internal:2322',
            'gallery.geocoder_url' => 'https://nominatim.openstreetmap.org',
        ]);
        Http::fake([
            'photon.internal:2322/reverse*' => Http::response([
                'features' => [['properties' => [
                    'street' => 'Rue de Rivoli', 'housenumber' => '10', 'city' => 'Paris',
                    'postcode' => '75001', 'state' => 'Île-de-France', 'country' => 'France', 'countrycode' => 'FR',
                ]]],
            ], 200),
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'MUST NOT BE USED', 'address' => []], 200),
        ]);

        $r = app(ReverseGeocoder::class)->lookupDetailed(48.86, 2.35);

        $this->assertStringContainsString('Paris', (string) $r['display']);
        $this->assertSame('fr', $r['address']['country_code']);
        $this->assertSame('Rue de Rivoli', $r['address']['road']);
        // Covered by Photon → OSM is never contacted.
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'nominatim.openstreetmap.org'));
    }

    public function test_falls_back_to_nominatim_when_photon_has_no_coverage(): void
    {
        config([
            'gallery.photon_url' => 'http://photon.internal:2322',
            'gallery.geocoder_url' => 'https://nominatim.openstreetmap.org',
        ]);
        Http::fake([
            'photon.internal:2322/reverse*' => Http::response(['features' => []], 200), // outside imported regions
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Sydney NSW, Australia', 'address' => ['country' => 'Australia'],
            ], 200),
        ]);

        $r = app(ReverseGeocoder::class)->lookupDetailed(-33.86, 151.2);

        $this->assertSame('Sydney NSW, Australia', $r['display']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'nominatim.openstreetmap.org'));
    }

    public function test_without_photon_only_the_configured_nominatim_endpoint_is_used(): void
    {
        config(['gallery.photon_url' => '', 'gallery.geocoder_url' => 'https://geo.internal.example']);
        Http::fake([
            'geo.internal.example/*' => Http::response(['display_name' => 'Somewhere', 'address' => []], 200),
        ]);

        $r = app(ReverseGeocoder::class)->lookupDetailed(1.0, 2.0);

        $this->assertSame('Somewhere', $r['display']);
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://geo.internal.example/reverse'));
    }
}
