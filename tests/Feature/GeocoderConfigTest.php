<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Support\NominatimClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocoderConfigTest extends TestCase
{
    public function test_automatic_on_upload_geocoding_is_off_by_default(): void
    {
        // The privacy-safe default: no coordinate leaves the host automatically.
        $this->assertFalse((bool) config('gallery.geocode_on_upload'));
    }

    public function test_geocoder_requests_go_to_the_configured_endpoint(): void
    {
        // Pointing at a self-hosted instance keeps every lookup in-boundary.
        config([
            'gallery.geocoder_url' => 'https://geo.internal.example',
            'gallery.geocode_interval_ms' => 0,
        ]);
        Http::fake([
            'geo.internal.example/*' => Http::response(['display_name' => 'Somewhere', 'address' => []], 200),
        ]);

        $out = app(NominatimClient::class)->get('reverse', ['lat' => 1.0, 'lon' => 2.0]);

        $this->assertIsArray($out);
        $this->assertSame('Somewhere', $out['display_name']);
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://geo.internal.example/reverse'));
    }
}
