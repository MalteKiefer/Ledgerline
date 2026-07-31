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
            ->assertJsonCount(3, 'geometry')
            // OSRM leaves the extended fields null (backward-compatible shape).
            ->assertJsonPath('elevation', null)
            ->assertJsonPath('ascentM', null)
            ->assertJsonPath('descentM', null)
            ->assertJsonPath('surfaces', null);
    }

    public function test_graphhopper_engine_returns_elevation_and_surfaces(): void
    {
        config([
            'maps.route_engine' => 'graphhopper',
            'maps.route_upstream' => 'https://router.example.test',
            'maps.route_profile' => 'hike',
        ]);

        Http::fake([
            'router.example.test/route*' => Http::response([
                'paths' => [[
                    'distance' => 2000.5,
                    'time' => 1800000, // ms → 1800 s (whole → JSON int)
                    'ascend' => 120.5,
                    'descend' => 40.25,
                    // GraphHopper points_encoded=false, elevation=true → [lng, lat, ele].
                    'points' => ['coordinates' => [
                        [13.377, 52.516, 34.0],
                        [13.379, 52.517, 44.0],
                        [13.381, 52.518, 50.0],
                        [13.383, 52.519, 46.0],
                    ]],
                    'details' => [
                        // [fromIdx, toIdx, value] over the 4-point path.
                        'surface' => [
                            [0, 2, 'asphalt'],
                            [2, 3, 'gravel'],
                        ],
                        'road_class' => [
                            [0, 3, 'path'],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.519,13.383', $this->bearer())
            ->assertOk()
            ->assertJsonPath('distanceM', 2000.5)
            // Whole-number floats serialize to JSON integers.
            ->assertJsonPath('durationS', 1800)
            ->assertJsonPath('ascentM', 120.5)
            ->assertJsonPath('descentM', 40.25)
            ->assertJsonPath('geometry.0.0', 52.516)
            ->assertJsonPath('geometry.0.1', 13.377)
            ->assertJsonCount(4, 'geometry')
            // Elevation profile: distM/eleM pairs, first vertex at distance 0.
            ->assertJsonPath('elevation.0.distM', 0)
            ->assertJsonPath('elevation.0.eleM', 34)
            ->assertJsonCount(4, 'elevation')
            // Surfaces sorted desc by distance: asphalt (2 segments) before gravel.
            ->assertJsonPath('surfaces.0.surface', 'asphalt')
            ->assertJsonPath('surfaces.1.surface', 'gravel')
            ->assertJsonCount(2, 'surfaces');

        // asphalt (idx 0→2) must cover more distance than gravel (idx 2→3).
        $surfaces = $response->json('surfaces');
        $this->assertGreaterThan($surfaces[1]['distM'], $surfaces[0]['distM']);
        $this->assertGreaterThan(0.0, $surfaces[0]['distM']);
    }

    public function test_graphhopper_derives_ascent_from_deltas_when_fields_absent(): void
    {
        config([
            'maps.route_engine' => 'graphhopper',
            'maps.route_upstream' => 'https://router.example.test',
            'maps.route_profile' => 'hike',
        ]);

        Http::fake([
            'router.example.test/route*' => Http::response([
                'paths' => [[
                    'distance' => 500.0,
                    'time' => 600000,
                    // No ascend/descend fields → derive from ele deltas.
                    'points' => ['coordinates' => [
                        [13.377, 52.516, 10.0],
                        [13.379, 52.517, 30.0], // +20 climb
                        [13.381, 52.518, 25.0], // -5 descent
                    ]],
                ]],
            ], 200),
        ]);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            // Whole-number floats serialize to JSON integers.
            ->assertJsonPath('ascentM', 20)
            ->assertJsonPath('descentM', 5)
            ->assertJsonPath('surfaces', null); // no surface detail
    }

    public function test_graphhopper_malformed_response_falls_back_to_null(): void
    {
        config([
            'maps.route_engine' => 'graphhopper',
            'maps.route_upstream' => 'https://router.example.test',
        ]);

        Http::fake([
            'router.example.test/route*' => Http::response(['paths' => []], 200),
        ]);

        $this->getJson('/api/v1/maps/route?points=52.516,13.377;52.518,13.381', $this->bearer())
            ->assertOk()
            ->assertJsonPath('geometry', null)
            ->assertJsonPath('elevation', null);
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

    public function test_it_rejects_more_than_100_waypoints(): void
    {
        // The cap is 100 waypoints; 101 must be rejected.
        $points = collect(range(1, 101))
            ->map(fn (int $i): string => sprintf('52.%02d,13.%02d', $i % 90, $i % 90))
            ->implode(';');

        $this->getJson('/api/v1/maps/route?points='.urlencode($points), $this->bearer())
            ->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/maps/route?points=52.5,13.3;52.6,13.4')->assertUnauthorized();
    }
}
