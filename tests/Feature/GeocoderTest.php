<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Services\Files\ReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearby_coordinates_share_one_lookup(): void
    {
        AppSettings::current()->update(['gallery_geocode_grid_km' => 0.5]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Bayreuth'])]);

        $geo = app(ReverseGeocoder::class);
        // ~150 m apart: same 0.5 km grid cell.
        $a = $geo->lookup(50.0000, 11.0000);
        $b = $geo->lookup(50.0010, 11.0010);

        $this->assertSame('Bayreuth', $a);
        $this->assertSame('Bayreuth', $b);
        Http::assertSentCount(1);
    }
}
