<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPA-only cutover: the theme is applied client-side by the SPA (there is no
 * server-rendered theme class or inline theme-bootstrap script in the shell any
 * more), so these tests cover the theme *persistence* endpoint that the SPA and
 * the Blade UI both drive.
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_can_be_switched_and_persisted(): void
    {
        $user = $this->signIn();

        $this->post(route('theme.update'), ['theme' => 'dark'])->assertRedirect();
        $this->assertSame('dark', UserSetting::for($user->id)->theme);

        $this->post(route('theme.update'), ['theme' => 'bogus'])->assertSessionHasErrors('theme');
    }
}
