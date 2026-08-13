<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_icon_endpoint_requires_authentication(): void
    {
        $this->get(route('passwords.icon', ['domain' => 'example.com']))->assertRedirect(route('login'));
    }

    public function test_invalid_domain_returns_no_icon_without_fetching(): void
    {
        $this->actingAs(User::factory()->create());
        // Not a valid hostname → rejected before any outbound request.
        $this->getJson(route('passwords.icon', ['domain' => 'not a domain']))
            ->assertOk()->assertExactJson(['icon' => null]);
        $this->getJson(route('passwords.icon', ['domain' => '10.0.0.1']))
            ->assertOk()->assertExactJson(['icon' => null]);
    }
}
