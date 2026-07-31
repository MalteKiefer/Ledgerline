<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapsResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_short_link_by_following_the_redirect(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://www.google.com/maps/place/X/@48.5216,9.0576,15z',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('maps.resolve', ['url' => 'https://maps.app.goo.gl/abcd']))
            ->assertOk()
            ->assertJson(['lat' => 48.5216, 'lng' => 9.0576]);
    }

    public function test_a_redirect_off_google_is_refused(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://evil.example.com/maps/@1.0,2.0,15z',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('maps.resolve', ['url' => 'https://maps.app.goo.gl/abcd']))
            ->assertOk()
            ->assertJson(['lat' => null, 'lng' => null]);
    }

    public function test_a_non_google_url_is_refused_without_fetching(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson(route('maps.resolve', ['url' => 'https://evil.example.com/x']))
            ->assertOk()
            ->assertJson(['lat' => null, 'lng' => null]);

        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson(route('maps.resolve', ['url' => 'https://maps.app.goo.gl/abcd']))
            ->assertRedirect(route('login'));
    }
}
