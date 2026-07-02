<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_vault_idle_timeout_can_be_configured(): void
    {
        $this->signIn();

        $this->get(route('settings.security.edit'))->assertOk();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 5])
            ->assertRedirect(route('settings.security.edit'));

        $this->assertSame(5, CompanyProfile::current()->vault_idle_minutes);
    }

    public function test_idle_timeout_is_bounded(): void
    {
        $this->signIn();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 0])
            ->assertSessionHasErrors('vault_idle_minutes');
    }
}
