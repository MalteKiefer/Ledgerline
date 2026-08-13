<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Files\ReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GeoSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_mapped_and_capped_results(): void
    {
        $this->signIn();

        // 10 upstream matches → the controller caps the response at 8.
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $rows[] = ['display' => "Place $i", 'lat' => 50.5 + $i, 'lon' => 8.25 + $i];
        }
        $this->mock(ReverseGeocoder::class, function (Mockery\MockInterface $m) use ($rows): void {
            $m->shouldReceive('search')->once()->with('Berlin')->andReturn($rows);
        });

        $res = $this->getJson(route('geo.search', ['q' => 'Berlin']))->assertOk();
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $res->assertJsonCount(8, 'results');
        $res->assertJsonPath('results.0.display', 'Place 0');
        $res->assertJsonPath('results.0.lat', 50.5);
        $res->assertJsonPath('results.0.lon', 8.25);
    }

    public function test_short_query_returns_empty_without_upstream_call(): void
    {
        $this->signIn();

        // A < 3 char query must NOT reach the geocoder (politeness + no lookups).
        $this->mock(ReverseGeocoder::class, function (Mockery\MockInterface $m): void {
            $m->shouldNotReceive('search');
        });

        $this->getJson(route('geo.search', ['q' => 'be']))
            ->assertOk()
            ->assertExactJson(['results' => []]);

        // A blank query too.
        $this->getJson(route('geo.search', ['q' => '  ']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_search_requires_authentication(): void
    {
        // The /api/v1 twin is Sanctum-guarded → 401 without a bearer.
        $this->getJson(route('api.geo.search', ['q' => 'Berlin']))->assertUnauthorized();
        // The web twin is behind the auth middleware (redirects a guest).
        $this->get(route('geo.search', ['q' => 'Berlin']))->assertRedirect();
    }
}
