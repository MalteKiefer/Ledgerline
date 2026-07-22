<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapsRouteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'maps.route_upstream' => 'https://router.example.test',
            'maps.route_profile' => 'foot',
        ]);
    }

    /** A real first-party device bearer, exactly as pairing mints it. */
    private function bearer(): array
    {
        $token = User::factory()->create()->createToken('cli', ['device'])->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_device_token_gets_a_snapped_route(): void
    {
        Http::fake([
            'router.example.test/route/v1/foot/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 1234.5,
                    'duration' => 987.6,
                    // OSRM GeoJSON is [lng, lat]; the controller flips to [lat, lng].
                    'geometry' => ['coordinates' => [
                        [13.377, 52.516],
                        [13.379, 52.517],
                        [13.381, 52.518],
                    ]],
                ]],
            ], 200),
        ]);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            ->assertJsonPath('distanceM', 1234.5)
            ->assertJsonPath('durationS', 987.6)
            ->assertJsonPath('geometry.0.0', 52.516)
            ->assertJsonPath('geometry.0.1', 13.377)
            ->assertJsonCount(3, 'geometry');
    }

    public function test_disabled_upstream_returns_null_geometry(): void
    {
        config(['maps.route_upstream' => '']);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            ->assertJsonPath('geometry', null);
    }

    public function test_unreachable_upstream_falls_back_to_null_geometry(): void
    {
        Http::fake(['router.example.test/*' => Http::response('down', 503)]);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            ->assertJsonPath('geometry', null);
    }

    public function test_no_route_result_falls_back_to_null_geometry(): void
    {
        Http::fake([
            'router.example.test/*' => Http::response(['code' => 'NoRoute', 'routes' => []], 200),
        ]);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            ->assertJsonPath('geometry', null);
    }

    public function test_it_validates_input(): void
    {
        // Missing points.
        $this->getJson('/api/v1/maps/route', $this->bearer())->assertStatus(422);
        // Only one valid waypoint (fewer than 2).
        $this->getJson('/api/v1/maps/route?points=52.5,13.3', $this->bearer())->assertStatus(422);
        // Out-of-range coordinates are dropped → fewer than 2 remain → 422.
        $this->getJson('/api/v1/maps/route?points=999,0;0,999', $this->bearer())->assertStatus(422);
    }

    public function test_it_rejects_more_than_25_waypoints(): void
    {
        $points = collect(range(1, 26))
            ->map(fn (int $i): string => sprintf('52.%d,13.%d', $i, $i))
            ->implode(';');

        $this->getJson('/api/v1/maps/route?points='.urlencode($points), $this->bearer())
            ->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/maps/route?points=52.5,13.3;52.6,13.4')->assertUnauthorized();
    }
}
