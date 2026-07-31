<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GalleryReverseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'gallery.photon_url' => 'http://photon.internal:2322',
            'gallery.geocoder_url' => 'https://nominatim.openstreetmap.org',
            'gallery.geocode_grid_km' => 0,
            'gallery.geocode_interval_ms' => 0,
        ]);
    }

    /** A real first-party device bearer, exactly as pairing mints it. */
    private function bearer(): array
    {
        $token = User::factory()->create()->createToken('cli', ['device'])->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_device_token_can_reverse_geocode_a_coordinate(): void
    {
        Http::fake([
            'photon.internal:2322/reverse*' => Http::response([
                'features' => [['properties' => [
                    'name' => 'Ebertstraße', 'city' => 'Berlin', 'postcode' => '10117',
                    'country' => 'Deutschland', 'countrycode' => 'DE',
                ]]],
            ], 200),
        ]);

        $this->getJson('/api/v1/gallery/reverse?lat=52.516&lng=13.377', $this->bearer())
            ->assertOk()
            ->assertJsonPath('address.country_code', 'de')
            ->assertJson(fn ($json) => $json->where('place', fn ($p) => str_contains((string) $p, 'Berlin'))->etc());
    }

    public function test_it_validates_coordinate_bounds(): void
    {
        $this->getJson('/api/v1/gallery/reverse?lat=999&lng=0', $this->bearer())->assertStatus(422);
        $this->getJson('/api/v1/gallery/reverse?lat=0&lng=999', $this->bearer())->assertStatus(422);
        $this->getJson('/api/v1/gallery/reverse?lat=0', $this->bearer())->assertStatus(422); // missing lng
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/gallery/reverse?lat=52.5&lng=13.3')->assertUnauthorized();
    }
}
