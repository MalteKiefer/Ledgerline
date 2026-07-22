<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapTileRelayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'maps.tile_upstream' => 'http://tiles:8080',
            'maps.style' => 'basic',
        ]);
    }

    public function test_tile_relays_upstream_bytes_with_cache_headers(): void
    {
        Http::fake([
            'tiles:8080/styles/basic/3/4/5.png' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->signIn();
        $res = $this->get(route('maps.tile', ['z' => 3, 'x' => 4, 'y' => 5]));
        $res->assertOk();
        $this->assertSame('PNGBYTES', $res->getContent());
        $res->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $res->headers->get('Cache-Control'));
    }

    public function test_tile_validates_zoom_and_coordinate_bounds(): void
    {
        Http::fake();
        $this->signIn();

        // z out of range (max 22).
        $this->get('/maps/tiles/23/0/0')->assertNotFound();
        // x out of range for z=1 (max index is 1).
        $this->get('/maps/tiles/1/2/0')->assertNotFound();
        // y out of range for z=1.
        $this->get('/maps/tiles/1/0/2')->assertNotFound();

        // No upstream tile fetch should have happened for a rejected coordinate.
        Http::assertNothingSent();
    }

    public function test_tile_returns_404_when_upstream_unset(): void
    {
        config(['maps.tile_upstream' => '']);
        Http::fake();
        $this->signIn();

        $this->get(route('maps.tile', ['z' => 2, 'x' => 1, 'y' => 1]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_tile_returns_404_when_upstream_errors(): void
    {
        Http::fake([
            'tiles:8080/*' => Http::response('nope', 500),
        ]);
        $this->signIn();

        $this->get(route('maps.tile', ['z' => 2, 'x' => 1, 'y' => 1]))->assertNotFound();
    }

    public function test_style_rewrites_tile_urls_to_same_origin(): void
    {
        Http::fake([
            'tiles:8080/styles/basic/style.json' => Http::response([
                'version' => 8,
                'sources' => [
                    'osm' => [
                        'type' => 'raster',
                        'tiles' => ['http://tiles:8080/styles/basic/{z}/{x}/{y}.png'],
                        'url' => 'http://tiles:8080/data/basic.json',
                    ],
                ],
                'layers' => [],
            ], 200),
        ]);
        $this->signIn();

        $res = $this->getJson(route('maps.style'))->assertOk();
        $tiles = $res->json('sources.osm.tiles');
        $this->assertIsArray($tiles);
        $this->assertStringContainsString('/maps/tiles/{z}/{x}/{y}', $tiles[0]);
        $this->assertStringNotContainsString('tiles:8080', $tiles[0]);
        // The upstream-absolute `url` reference is dropped so the host never leaks.
        $this->assertNull($res->json('sources.osm.url'));
    }

    public function test_maps_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/maps/style')->assertUnauthorized();
        $this->getJson('/api/v1/maps/tiles/2/1/1')->assertUnauthorized();
    }
}
