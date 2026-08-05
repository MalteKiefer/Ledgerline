<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every server-rendered page so a broken Blade component usage (e.g. an
 * Alpine `:binding` that Blade would evaluate as PHP, or a malformed component
 * attribute) surfaces as a 500 in CI rather than in the browser. Guards the shared
 * <x-button>/<x-icon-button> primitives used across the whole UI.
 */
class DesignSmokeRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_pages_render(): void
    {
        $u = User::factory()->create();
        $routes = [
            '/dashboard', '/health', '/finance', '/notes', '/todos', '/bookmarks',
            '/contacts', '/mail', '/explore', '/calendar', '/gallery', '/files', '/passwords',
            '/profile', '/profile/account', '/profile/devices', '/profile/sessions',
            '/profile/encryption', '/profile/security', '/profile/appearance', '/profile/calendar',
            '/profile/export', '/profile/danger',
            '/settings/files', '/settings/contacts', '/settings/paperless',
        ];
        foreach ($routes as $r) {
            $this->actingAs($u)->get($r)->assertOk();
        }
    }

    public function test_admin_settings_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $routes = [
            '/settings', '/settings/company', '/settings/security', '/settings/system',
            '/settings/security-log', '/settings/files/limits', '/settings/notifications',
            '/settings/backup', '/settings/users', '/settings/groups',
        ];
        foreach ($routes as $r) {
            $this->actingAs($admin)->get($r)->assertOk();
        }
    }
}
