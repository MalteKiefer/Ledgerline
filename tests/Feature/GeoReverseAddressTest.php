<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Files\ReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * GET /geo/reverse must serialise `address` as a JSON OBJECT ({}) even when
 * empty — PHP would otherwise json_encode an empty array as [] (a JSON array),
 * which breaks a strictly-typed native client (iOS/Android).
 */
class GeoReverseAddressTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeocoder(array $result): void
    {
        $mock = Mockery::mock(ReverseGeocoder::class);
        $mock->shouldReceive('lookupDetailed')->andReturn($result);
        $this->app->instance(ReverseGeocoder::class, $mock);
    }

    public function test_empty_address_is_a_json_object_not_an_array(): void
    {
        $this->fakeGeocoder(['display' => null, 'address' => []]);
        $user = User::factory()->create();
        $token = $user->createToken('t', ['device'])->plainTextToken;

        $res = $this->getJson('/api/v1/geo/reverse?lat=52.5&lng=13.4', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('place', null);

        // The decisive check: the wire bytes must contain "address":{} not "address":[].
        $this->assertStringContainsString('"address":{}', $res->getContent());
        $this->assertStringNotContainsString('"address":[]', $res->getContent());
    }

    public function test_filled_address_is_a_json_object(): void
    {
        $this->fakeGeocoder(['display' => 'Berlin', 'address' => ['city' => 'Berlin', 'country' => 'Germany']]);
        $user = User::factory()->create();
        $token = $user->createToken('t', ['device'])->plainTextToken;

        $this->getJson('/api/v1/geo/reverse?lat=52.5&lng=13.4', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('address.city', 'Berlin')
            ->assertJsonPath('address.country', 'Germany');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
