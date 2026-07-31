<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use App\Support\ThemeBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_can_be_switched_and_is_rendered_on_the_html_tag(): void
    {
        $user = $this->signIn();

        // Default: system.
        $this->get(route('dashboard'))->assertOk()->assertSee('data-theme="system"', false);

        $this->post(route('theme.update'), ['theme' => 'dark'])->assertRedirect();
        $this->assertSame('dark', UserSetting::for($user->id)->theme);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-theme="dark"', false);

        $this->post(route('theme.update'), ['theme' => 'bogus'])->assertSessionHasErrors('theme');
    }

    public function test_the_bootstrap_script_is_emitted_and_csp_hash_matches(): void
    {
        $this->signIn();
        $response = $this->get(route('dashboard'))->assertOk();

        // The inline script must be byte-identical to the hashed constant.
        $response->assertSee('<script>'.ThemeBootstrap::SCRIPT.'</script>', false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString(ThemeBootstrap::cspHash(), $csp);
    }
}
