<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PasswordIconController;
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

    public function test_parses_declared_icon_links_ranked_apple_touch_and_biggest_first(): void
    {
        // Real-world WordPress <head>: only <link rel="icon"> is declared, no bare
        // /favicon.ico — the exact case that yielded no logo before this parser.
        $html = <<<'HTML'
        <link rel="icon" href="https://www.example.de/wp-content/uploads/cropped-32x32.png" sizes="32x32" />
        <link rel="icon" href="https://www.example.de/wp-content/uploads/cropped-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="https://www.example.de/wp-content/uploads/cropped-180x180.png" />
        HTML;

        $icons = PasswordIconController::parseIconLinks($html, 'https://www.example.de/');

        $this->assertSame([
            'https://www.example.de/wp-content/uploads/cropped-180x180.png', // apple-touch → first
            'https://www.example.de/wp-content/uploads/cropped-192x192.png', // bigger size next
            'https://www.example.de/wp-content/uploads/cropped-32x32.png',
        ], $icons);
    }

    public function test_resolves_relative_and_protocol_relative_icon_hrefs(): void
    {
        $html = '<link rel="icon" href="/assets/favicon.png"><link rel="apple-touch-icon" href="//cdn.example.de/logo.png" sizes="180x180">';
        $icons = PasswordIconController::parseIconLinks($html, 'https://example.de/');

        $this->assertSame([
            'https://cdn.example.de/logo.png',   // apple-touch, protocol-relative → resolved to https
            'https://example.de/assets/favicon.png', // root-relative → resolved against base host
        ], $icons);
    }
}
